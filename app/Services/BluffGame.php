<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Validation\ValidationException;

class BluffGame
{
    /** @param array<string, mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        if ($room->players()->get()->filter(fn ($player) => $player->connected())->count() < 3) {
            throw ValidationException::withMessages(['players' => __('rooms.bluff_minimum')]);
        }
        $counts = app(RoomContentCatalog::class)->availability($room, ContentType::Bluff, $settings['packs']);
        if (($settings['allow_repeats'] ?? false) ? $counts['total'] < 1 : $counts['unseen'] < $settings['rounds']) {
            throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
        }
        $game = Game::create(['room_id' => $room->id, 'type' => 'bluff', 'settings' => $settings, 'state' => ['scores' => [], 'number' => 0]]);
        $this->nextRound($room, $game);

        return $game;
    }

    private function nextRound(Room $room, Game $game): void
    {
        $players = $room->players()->get()->filter(fn ($player) => $player->connected())->modelKeys();
        if (count($players) < 3) {
            $state = $game->state;
            $state['phase'] = 'waiting';
            app(GameEngine::class)->save($game, ['state' => $state]);

            return;
        }
        try {
            $content = app(RoomContentCatalog::class)->reveal($room, ContentType::Bluff, $game->settings['packs'], 1, $game->settings['allow_repeats'] ?? false)->firstOrFail();
        } catch (ValidationException $exception) {
            if (! isset($game->state['round'])) {
                throw $exception;
            }
            $state = $game->state;
            $state['previous_phase'] = 'reveal';
            $state['phase'] = 'paused';
            $state['remaining'] = 0.0;
            $state['exhausted'] = true;
            $state['manual_pause'] = true;
            app(GameEngine::class)->save($game, ['state' => $state]);

            return;
        }
        app(GameEngine::class)->save($game, ['state' => [
            'scores' => $game->state['scores'] + array_fill_keys($players, 0),
            'number' => $game->state['number'] + 1, 'phase' => 'writing', 'previous_phase' => 'writing',
            'remaining' => 0.0, 'manual_pause' => false, 'deadline' => app(GameEngine::class)->time() + $game->settings['duration'],
            'round' => ['content_id' => $content->id, 'question' => $content->payload['question'], 'truth' => $content->payload['answer'], 'participants' => $players,
                'drafts' => [], 'submitted' => [], 'entries' => [], 'index' => 0, 'votes' => [], 'awards' => []],
        ]]);
    }

    /** @param array<string, mixed> $state */
    private function eligible(RoomPlayer $player, array $state): bool
    {
        return $player->connected() && in_array($player->id, $state['round']['participants'], true)
            && ! in_array($player->id, $state['excluded'] ?? [], true);
    }

    private function current(Room $room, RoomPlayer $player, int $gameId, int $round, string $phase): Game
    {
        $game = app(GameEngine::class)->tick($room);
        abort_unless($game !== null && $game->type === 'bluff' && $game->id === $gameId && $game->state['number'] === $round && $game->state['phase'] === $phase, 409);
        abort_unless($this->eligible($player, $game->state), 403);

        return $game;
    }

    public function write(Room $room, RoomPlayer $player, string $text, bool $submit, int $gameId, int $round): void
    {
        $game = $this->current($room, $player, $gameId, $round, 'writing');
        $state = $game->state;
        abort_if(isset($state['round']['submitted'][$player->id]), 409);
        if ($submit && app(BluffAnswers::class)->normalize($text) === '') {
            throw ValidationException::withMessages(['text' => __('rooms.bluff_empty')]);
        }
        $state['round']['drafts'][$player->id] = $text;
        if ($submit) {
            $state['round']['submitted'][$player->id] = $text;
        }
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    public function vote(Room $room, RoomPlayer $player, string $choice, int $gameId, int $round): void
    {
        $game = $this->current($room, $player, $gameId, $round, 'voting');
        $state = $game->state;
        abort_if(isset($state['round']['votes'][$player->id]), 409);
        $truth = $this->entry($state['round']['entries'], 'correct', true);
        abort_if(in_array($player->id, $truth['authors'], true), 403);
        $entry = $this->entry($state['round']['entries'], 'id', $choice);
        if ($entry === null) {
            throw ValidationException::withMessages(['choice' => __('rooms.bluff_invalid_vote')]);
        }
        abort_if(in_array($player->id, $entry['authors'], true), 403);
        $state['round']['votes'][$player->id] = $choice;
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function entry(array $entries, string $key, mixed $value): ?array
    {
        foreach ($entries as $entry) {
            if ($entry[$key] === $value) {
                return $entry;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $state */
    private function finishRound(Room $room, Game $game, array $state): void
    {
        $state['round']['awards'] = app(BluffAnswers::class)->awards($state['round']['entries'], $state['round']['votes']);
        foreach ($state['round']['awards'] as $id => $points) {
            $state['scores'][$id] = ($state['scores'][$id] ?? 0) + $points;
            $room->players()->whereKey($id)->increment('score', $points);
        }
        $state['phase'] = 'reveal';
        $state['deadline'] = app(GameEngine::class)->time() + 8;
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    public function tick(Room $room, Game $game): Game
    {
        $state = $game->state;
        $time = app(GameEngine::class)->time();
        $players = $room->players()->get()->filter(fn ($player) => $this->eligible($player, $state));
        if ($state['phase'] === 'writing' && ($state['deadline'] <= $time || $players->every(fn ($player) => isset($state['round']['submitted'][$player->id])))) {
            $entries = app(BluffAnswers::class)->build($state['round']['truth'], $state['round']['submitted']);
            $state['round']['entries'] = $entries;
            if (count($entries) === 1) {
                $this->finishRound($room, $game, $state);

                return $game;
            }
            $state['phase'] = 'presenting';
            $state['deadline'] = $time + 3;
            app(GameEngine::class)->save($game, ['state' => $state]);
        } elseif ($state['phase'] === 'presenting' && $state['deadline'] <= $time) {
            do {
                $state['round']['index']++;
                if ($state['round']['index'] >= count($state['round']['entries'])) {
                    $state['phase'] = 'voting';
                    $state['deadline'] += 25;
                    break;
                }
                $state['deadline'] += 3;
            } while ($state['deadline'] <= $time);
            app(GameEngine::class)->save($game, ['state' => $state]);
        } elseif ($state['phase'] === 'voting') {
            $truth = $this->entry($state['round']['entries'], 'correct', true);
            if ($state['deadline'] <= $time || $players->filter(fn ($player) => ! in_array($player->id, $truth['authors'], true))->every(fn ($player) => isset($state['round']['votes'][$player->id]))) {
                $this->finishRound($room, $game, $state);
            }
        } elseif ($state['phase'] === 'reveal' && $state['deadline'] <= $time) {
            $target = $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target;
            if ($state['number'] >= $game->settings['rounds'] || $target) {
                $state['phase'] = 'results';
                app(GameEngine::class)->save($game, ['state' => $state, 'status' => 'finished']);
            } else {
                $this->nextRound($room, $game);
            }
        } elseif ($state['phase'] === 'waiting') {
            $this->nextRound($room, $game);
        }

        return $game;
    }

    /** @return array<string, mixed> */
    public function view(Room $room, Game $game, ?RoomPlayer $me): array
    {
        $state = $game->state;
        $round = $state['round'];
        $phase = in_array($state['phase'], ['paused', 'resuming'], true) ? $state['previous_phase'] : $state['phase'];
        $revealed = in_array($phase, ['reveal', 'results', 'waiting'], true);
        $truth = $this->entry($round['entries'], 'correct', true);
        $foundTruth = $phase !== 'writing' && $me !== null && in_array($me->id, $truth['authors'] ?? [], true);

        return ['id' => $game->id, 'type' => 'bluff', 'settings' => $game->settings, 'exhausted' => $state['exhausted'] ?? false,
            'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target,
            'phase' => $state['phase'], 'deadline' => $state['deadline'], 'scores' => $state['scores'],
            'round' => ['number' => $state['number'], 'total' => $game->settings['rounds'], 'question' => $round['question'], 'index' => $round['index'],
                'entries' => array_map(fn ($entry) => ['id' => $entry['id'], 'text' => $entry['text'], 'own' => $me !== null && in_array($me->id, $entry['authors'], true), 'correct' => $revealed ? $entry['correct'] : null, 'authors' => $revealed ? $entry['authors'] : [], 'voters' => $revealed ? array_map('intval', array_keys(array_filter($round['votes'], fn ($id) => $id === $entry['id']))) : []], $phase === 'writing' ? [] : $round['entries']),
                'awards' => $revealed ? $round['awards'] : []],
            'me' => $me === null ? null : ['eligible' => $this->eligible($me, $state), 'draft' => $round['drafts'][$me->id] ?? '',
                'submitted' => isset($round['submitted'][$me->id]), 'foundTruth' => $foundTruth, 'canVote' => $phase === 'voting' && $this->eligible($me, $state) && ! $foundTruth,
                'answered' => isset($round['votes'][$me->id]), 'choice' => $round['votes'][$me->id] ?? null,
                'points' => $revealed ? ($round['awards'][$me->id] ?? 0) : null]];
    }
}
