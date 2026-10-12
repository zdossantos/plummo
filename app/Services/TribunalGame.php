<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Validation\ValidationException;

class TribunalGame
{
    /** @return list<int> */
    private function players(Room $room): array
    {
        return array_values($room->players()->orderBy('id')->get()->filter(fn (RoomPlayer $p) => $p->connected())->map(fn (RoomPlayer $player) => $player->id)->all());
    }

    /** @param array<string, mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        if (count($this->players($room)) < 3) {
            throw ValidationException::withMessages(['type' => __('rooms.tribunal_minimum')]);
        }
        $topics = array_keys(__('tribunal.topics'));
        shuffle($topics);
        $game = Game::create(['room_id' => $room->id, 'type' => 'tribunal', 'settings' => [...$settings, 'packs' => []], 'state' => ['scores' => [], 'number' => 0, 'tour' => 0, 'queue' => [], 'topics' => $topics]]);
        $this->next($room, $game);

        return $game;
    }

    private function next(Room $room, Game $game): void
    {
        $state = $game->state;
        $players = $this->players($room);
        if (count($players) < 3) {
            $state['phase'] = 'waiting';
            $state['deadline'] = app(GameEngine::class)->time();
            app(GameEngine::class)->save($game, ['state' => $state]);

            return;
        }
        while (true) {
            if (isset($state['tourPlayers']) && count(array_intersect($state['tourPlayers'], $players)) < 3) {
                $state['queue'] = [];
            }
            if ($state['queue'] === []) {
                if ($state['tour'] >= $game->settings['rounds']) {
                    $state['phase'] = 'results';
                    app(GameEngine::class)->save($game, ['state' => $state, 'status' => 'finished']);

                    return;
                }
                $rotation = $players;
                shuffle($rotation);
                $state['tour']++;
                $state['tourPlayers'] = $rotation;
                foreach ($rotation as $i => $id) {
                    $state['queue'][] = [$id, $rotation[($i + 1) % count($rotation)]];
                }
            }
            $pair = array_shift($state['queue']);
            if (count(array_intersect($pair, $players)) === 2) {
                break;
            }
        }
        $participants = array_values(array_intersect($state['tourPlayers'], $players));
        if ($state['topics'] === []) {
            $state['topics'] = array_keys(__('tribunal.topics'));
            shuffle($state['topics']);
            if ($state['topics'][0] === ($state['round']['topic'] ?? null)) {
                $state['topics'][] = array_shift($state['topics']);
            }
        }
        $topic = array_shift($state['topics']);
        $state = [...$state, 'scores' => $state['scores'] + array_fill_keys($participants, 0), 'number' => $state['number'] + 1, 'excluded' => [], 'phase' => 'preparing', 'previous_phase' => 'preparing', 'remaining' => 0, 'manual_pause' => false, 'deadline' => app(GameEngine::class)->time() + 15, 'round' => ['topic' => $topic, 'advocates' => $pair, 'participants' => $participants, 'votes' => [], 'awards' => [], 'canceled' => false]];
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    /** @param array<string, mixed> $state */
    private function eligible(RoomPlayer $player, array $state): bool
    {
        return $player->connected() && in_array($player->id, $state['round']['participants'] ?? [], true) && ! in_array($player->id, $state['excluded'] ?? [], true);
    }

    /** @param array<string, mixed> $state */
    private function speaker(array $state, string $phase): ?int
    {
        if (! in_array($phase, ['speaking_first', 'speaking_second'], true)) {
            return null;
        }
        $first = ($state['number'] - 1) % 2;

        return $state['round']['advocates'][$phase === 'speaking_first' ? $first : 1 - $first];
    }

    private function advance(Game $game): void
    {
        $state = $game->state;
        $state['phase'] = match ($state['phase']) {
            'preparing' => 'speaking_first', 'speaking_first' => 'speaking_second', default => 'voting',
        };
        $state['deadline'] = app(GameEngine::class)->time() + ($state['phase'] === 'voting' ? 20 : $game->settings['duration']);
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    public function tick(Room $room, Game $game): Game
    {
        $state = $game->state;
        $phase = $state['phase'];
        if (in_array($phase, ['preparing', 'speaking_first', 'speaking_second', 'voting'], true)) {
            $players = $this->players($room);
            if (array_diff($state['round']['advocates'], array_diff($players, $state['excluded'] ?? [])) !== []) {
                $state['round']['canceled'] = true;
                $state['phase'] = 'reveal';
                $state['deadline'] = app(GameEngine::class)->time() + 6;
                app(GameEngine::class)->save($game, ['state' => $state]);

                return $game;
            }
        }
        if ($phase === 'waiting') {
            $this->next($room, $game);
        } elseif (in_array($phase, ['preparing', 'speaking_first', 'speaking_second'], true) && $state['deadline'] <= app(GameEngine::class)->time()) {
            $this->advance($game);
        } elseif ($phase === 'voting') {
            $waiting = $room->players()->get()->filter(fn (RoomPlayer $p) => $this->eligible($p, $state) && ! in_array($p->id, $state['round']['advocates'], true) && ! isset($state['round']['votes'][$p->id]));
            if ($waiting->isEmpty() || $state['deadline'] <= app(GameEngine::class)->time()) {
                $this->finish($room, $game);
            }
        } elseif ($phase === 'reveal' && $state['deadline'] <= app(GameEngine::class)->time()) {
            if ($room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target) {
                $state['phase'] = 'results';
                app(GameEngine::class)->save($game, ['state' => $state, 'status' => 'finished']);
            } else {
                $this->next($room, $game);
            }
        }

        return $game;
    }

    public function action(Room $room, RoomPlayer $player, int $id, int $number, string $action, ?int $choice): void
    {
        $game = app(GameEngine::class)->tick($room);
        abort_unless($game !== null && $game->status === 'active' && $game->type === 'tribunal' && $game->id === $id && $game->state['number'] === $number, 409);
        $state = $game->state;
        abort_unless($this->eligible($player, $state), 403);
        if ($action === 'finish') {
            abort_unless(in_array($state['phase'], ['speaking_first', 'speaking_second'], true), 409);
            abort_unless($this->speaker($state, $state['phase']) === $player->id, 403);
            $this->advance($game);
        } else {
            abort_unless($state['phase'] === 'voting', 409);
            abort_if(in_array($player->id, $state['round']['advocates'], true), 403);
            abort_if(isset($state['round']['votes'][$player->id]), 409);
            $state['round']['votes'][$player->id] = $choice;
            app(GameEngine::class)->save($game, ['state' => $state]);
            $this->tick($room, $game);
        }
    }

    private function finish(Room $room, Game $game): void
    {
        $state = $game->state;
        foreach ($state['round']['advocates'] as $choice => $id) {
            $count = count(array_filter($state['round']['votes'], fn ($vote) => $vote === $choice));
            $points = app(Scoring::class)->votes($count);
            $state['round']['awards'][$id] = $points;
            $state['scores'][$id] += $points;
            if ($points > 0) {
                $room->players()->whereKey($id)->increment('score', $points);
            }
        }
        $state['phase'] = 'reveal';
        $state['deadline'] = app(GameEngine::class)->time() + 6;
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    /** @return array<string, mixed> */
    public function view(Room $room, Game $game, ?RoomPlayer $me): array
    {
        $state = $game->state;
        $round = $state['round'];
        $phase = in_array($state['phase'], ['paused', 'resuming'], true) ? $state['previous_phase'] : $state['phase'];
        $revealed = in_array($phase, ['reveal', 'results'], true);
        $speaker = $this->speaker($state, $phase);
        $counts = array_map(fn ($choice) => count(array_filter($round['votes'], fn ($vote) => $vote === $choice)), [0, 1]);
        $eligible = $me !== null && $this->eligible($me, $state);
        $camp = $me === null ? false : array_search($me->id, $round['advocates'], true);

        return ['id' => $game->id, 'type' => 'tribunal', 'settings' => $game->settings, 'exhausted' => false, 'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target, 'phase' => $state['phase'], 'deadline' => $state['deadline'], 'scores' => $state['scores'], 'round' => ['number' => $state['number'], 'total' => $state['number'] + count($state['queue']), 'tour' => $state['tour'], 'tours' => $game->settings['rounds'], 'question' => __('tribunal.topics.'.$round['topic']), 'advocates' => $round['advocates'], 'speakerId' => $speaker, 'votes' => $revealed && ! $round['canceled'] ? $round['votes'] : [], 'counts' => $revealed && ! $round['canceled'] ? $counts : [], 'awards' => $revealed ? $round['awards'] : [], 'canceled' => $round['canceled']], 'me' => $me === null ? null : ['eligible' => $eligible, 'camp' => $camp === false ? null : $camp, 'canSpeak' => $eligible && $speaker === $me->id && $state['phase'] === $phase, 'canVote' => $eligible && $camp === false && $state['phase'] === 'voting', 'answered' => isset($round['votes'][$me->id]), 'choice' => $round['votes'][$me->id] ?? null, 'points' => $revealed ? ($round['awards'][$me->id] ?? 0) : null]];
    }
}
