<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MiniGames
{
    /** @param array<string, mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        $available = app(MiniGameScenes::class)->available($this->players($room)->count());
        if (array_diff($settings['trials'], $available) !== []) {
            throw ValidationException::withMessages(['trials' => __('rooms.mini_unavailable')]);
        }
        $game = Game::create(['room_id' => $room->id, 'type' => 'mini_games', 'settings' => [...$settings, 'packs' => []], 'state' => ['scores' => [], 'number' => 0, 'bag' => []]]);
        $this->next($room, $game);

        return $game;
    }

    /** @return Collection<int, RoomPlayer> */
    private function players(Room $room): Collection
    {
        return $room->players()->orderBy('id')->get()->filter(fn (RoomPlayer $player) => $player->connected())->values();
    }

    private function next(Room $room, Game $game): void
    {
        $players = $this->players($room)->modelKeys();
        $available = array_values(array_intersect($game->settings['trials'], app(MiniGameScenes::class)->available(count($players))));
        $state = $game->state;
        if ($available === []) {
            $state['phase'] = 'waiting';
            $state['previous_phase'] = 'waiting';
            $state['deadline'] = app(GameEngine::class)->time();
            app(GameEngine::class)->save($game, ['state' => $state]);

            return;
        }
        $bag = array_values(array_intersect($state['bag'], $available));
        if ($bag === []) {
            $bag = $available;
            shuffle($bag);
            if (count($bag) > 1 && $bag[0] === ($state['trial'] ?? null)) {
                [$bag[0], $bag[1]] = [$bag[1], $bag[0]];
            }
        }
        $trial = array_shift($bag);
        $payload = app(MiniGameScenes::class)->generate($trial);
        $phase = $trial === 'author' ? 'writing' : ($payload['observe'] > 0 ? 'observing' : 'answer');
        $duration = match ($phase) {
            'writing' => 30, 'observing' => $payload['observe'], default => $game->settings['duration'],
        };
        $state = [...$state, 'scores' => $state['scores'] + array_fill_keys($players, 0), 'number' => $state['number'] + 1, 'bag' => $bag, 'trial' => $trial, 'excluded' => [], 'phase' => $phase, 'previous_phase' => $phase, 'remaining' => 0, 'manual_pause' => false, 'deadline' => app(GameEngine::class)->time() + $duration, 'round' => ['payload' => $payload, 'participants' => $players, 'answers' => [], 'awards' => [], 'drafts' => [], 'submitted' => [], 'author' => null]];
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    public function tick(Room $room, Game $game): Game
    {
        $state = $game->state;
        if ($state['phase'] === 'waiting') {
            $this->next($room, $game);
        } elseif ($state['phase'] === 'observing' && $state['deadline'] <= app(GameEngine::class)->time()) {
            $state['phase'] = 'answer';
            $state['deadline'] = app(GameEngine::class)->time() + $game->settings['duration'];
            app(GameEngine::class)->save($game, ['state' => $state]);
        } elseif ($state['phase'] === 'writing') {
            $waiting = $this->players($room)->filter(fn ($p) => $this->eligible($p, $state) && ! isset($state['round']['submitted'][$p->id]));
            if ($waiting->isEmpty() || $state['deadline'] <= app(GameEngine::class)->time()) {
                $entries = array_filter($state['round']['drafts'], fn ($text) => trim($text) !== '');
                if ($entries === []) {
                    $state['phase'] = 'reveal';
                    $state['deadline'] = app(GameEngine::class)->time() + 4;
                } else {
                    $author = (int) array_rand($entries);
                    $state['round']['author'] = $author;
                    $state['round']['text'] = $entries[$author];
                    $state['round']['payload']['choices'] = $room->players()->whereIn('id', $state['round']['participants'])->orderBy('id')->pluck('name')->map(fn ($name, $index) => ($index + 1).' · '.$name)->all();
                    $ids = $room->players()->whereIn('id', $state['round']['participants'])->orderBy('id')->modelKeys();
                    $state['round']['payload']['correct'] = array_search($author, $ids, true);
                    $state['phase'] = 'answer';
                    $state['deadline'] = app(GameEngine::class)->time() + $game->settings['duration'];
                }
                app(GameEngine::class)->save($game, ['state' => $state]);
            }
        } elseif ($state['phase'] === 'answer') {
            $waiting = $this->players($room)->filter(fn ($p) => $this->eligible($p, $state) && ! isset($state['round']['answers'][$p->id]));
            if ($waiting->isEmpty() || $state['deadline'] <= app(GameEngine::class)->time()) {
                $this->finish($room, $game);
            }
        } elseif ($state['phase'] === 'reveal' && $state['deadline'] <= app(GameEngine::class)->time()) {
            if ($state['number'] >= $game->settings['rounds'] || ($room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target)) {
                $state['phase'] = 'results';
                app(GameEngine::class)->save($game, ['state' => $state, 'status' => 'finished']);
            } else {
                $this->next($room, $game);
            }
        }

        return $game;
    }

    /** @param array<string, mixed> $state */
    private function eligible(RoomPlayer $player, array $state): bool
    {
        return $player->connected() && in_array($player->id, $state['round']['participants'], true) && ! in_array($player->id, $state['excluded'] ?? [], true) && ($state['phase'] === 'writing' || $state['round']['author'] !== $player->id);
    }

    private function current(Room $room, RoomPlayer $player, int $id, int $round, string $phase): Game
    {
        $game = app(GameEngine::class)->tick($room);
        abort_unless($game !== null && $game->type === 'mini_games' && $game->id === $id && $game->state['number'] === $round && $game->state['phase'] === $phase, 409);
        abort_unless($this->eligible($player, $game->state), 403);

        return $game;
    }

    public function write(Room $room, RoomPlayer $player, int $id, int $round, string $text, bool $submit): void
    {
        $game = $this->current($room, $player, $id, $round, 'writing');
        $state = $game->state;
        abort_if(isset($state['round']['submitted'][$player->id]), 409);
        $state['round']['drafts'][$player->id] = trim($text);
        if ($submit) {
            $state['round']['submitted'][$player->id] = true;
        }
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    public function answer(Room $room, RoomPlayer $player, int $id, int $round, int $choice): void
    {
        $game = $this->current($room, $player, $id, $round, 'answer');
        if ($choice >= count($game->state['round']['payload']['choices'])) {
            throw ValidationException::withMessages(['choice' => __('rooms.mini_invalid_choice')]);
        }
        $state = $game->state;
        abort_if(isset($state['round']['answers'][$player->id]), 409);
        $state['round']['answers'][$player->id] = ['choice' => $choice, 'at' => app(GameEngine::class)->time()];
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    private function finish(Room $room, Game $game): void
    {
        $state = $game->state;
        $correct = collect($game->answers())->filter(fn ($answer) => isset($answer['choice']) && $answer['choice'] === $state['round']['payload']['correct'])->sortBy('at');
        $count = count($state['round']['participants']) - ($state['round']['author'] !== null ? 1 : 0);
        $rank = 1;
        foreach ($correct->groupBy(fn ($answer) => sprintf('%.6f', $answer['at']), true) as $tied) {
            $points = app(Scoring::class)->rapid($count, $rank, $tied->count());
            foreach ($tied as $id => $answer) {
                $state['round']['awards'][$id] = $points;
                $state['scores'][$id] += $points;
                $room->players()->whereKey($id)->increment('score', $points);
            }
            $rank += $tied->count();
        }
        $state['phase'] = 'reveal';
        $state['deadline'] = app(GameEngine::class)->time() + 4;
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    private function label(string $kind, string $value): string
    {
        if ($kind === 'color') {
            return __('plummo.palette.'.$value);
        }
        if ($kind === 'accessory') {
            return __('plummo.items.'.$value);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function view(Room $room, Game $game, ?RoomPlayer $me, bool $screen): array
    {
        $state = $game->state;
        $round = $state['round'];
        $payload = $round['payload'];
        $phase = in_array($state['phase'], ['paused', 'resuming'], true) ? $state['previous_phase'] : $state['phase'];
        $revealed = in_array($phase, ['reveal', 'results'], true);
        $elapsed = $phase === 'observing' ? max(0, $payload['observe'] - (in_array($state['phase'], ['paused', 'resuming'], true) ? $state['remaining'] : max(0, $state['deadline'] - app(GameEngine::class)->time()))) : $payload['observe'];
        $args = $payload['args'];
        if ($state['trial'] === 'hide') {
            $args['color'] = $this->label('color', $args['color']);
            $args['accessory'] = $this->label('accessory', $args['accessory']);
        }
        $scene = $screen && ($state['trial'] !== 'memory' || $phase === 'observing' || $revealed) ? $payload['scene'] : null;

        return ['id' => $game->id, 'type' => 'mini_games', 'settings' => $game->settings, 'exhausted' => false, 'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target, 'phase' => $state['phase'], 'deadline' => $state['deadline'], 'scores' => $state['scores'], 'round' => ['number' => $state['number'], 'total' => $game->settings['rounds'], 'trial' => $state['trial'], 'question' => $phase === 'observing' ? __('rooms.mini_observe_'.$state['trial']) : __('rooms.'.$payload['question'], $args), 'text' => $phase === 'writing' ? null : ($round['text'] ?? null), 'scene' => $scene, 'elapsed' => $elapsed, 'observeDuration' => $payload['observe'], 'scenePhase' => $phase, 'participants' => $state['trial'] === 'author' ? $round['participants'] : [], 'choices' => in_array($phase, ['observing', 'writing'], true) ? [] : array_map(fn ($value) => $this->label($payload['choiceKind'], $value), $payload['choices']), 'correct' => $revealed ? $payload['correct'] : null, 'answers' => $revealed ? array_map(fn ($answer) => $answer['choice'], $round['answers']) : [], 'awards' => $revealed ? $round['awards'] : []], 'me' => $me === null ? null : ['authorNumber' => ($index = array_search($me->id, array_values($round['participants']), true)) === false ? null : $index + 1, 'eligible' => $this->eligible($me, $state), 'draft' => $round['drafts'][$me->id] ?? '', 'submitted' => isset($round['submitted'][$me->id]), 'answered' => isset($round['answers'][$me->id]), 'choice' => $round['answers'][$me->id]['choice'] ?? null, 'points' => $revealed ? ($round['awards'][$me->id] ?? 0) : null]];
    }
}
