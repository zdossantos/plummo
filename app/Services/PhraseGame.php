<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhraseGame
{
    /** @param array<string, mixed> $settings */
    public function start(Room $room, array $settings): Game
    {
        if ($room->players()->get()->filter(fn ($player) => $player->connected())->count() < 3) {
            throw ValidationException::withMessages(['players' => __('rooms.phrase_minimum')]);
        }
        $counts = app(RoomContentCatalog::class)->availability($room, ContentType::Phrase, $settings['packs']);
        if (($settings['allow_repeats'] ?? false) ? $counts['total'] < 1 : $counts['unseen'] < $settings['rounds']) {
            throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
        }
        $game = Game::create(['room_id' => $room->id, 'type' => 'phrase', 'settings' => $settings, 'state' => ['scores' => [], 'number' => 0]]);
        $this->nextRound($room, $game);

        return $game;
    }

    private function nextRound(Room $room, Game $game): void
    {
        $players = $room->players()->get()->filter(fn ($player) => $player->connected())->modelKeys();
        try {
            $content = app(RoomContentCatalog::class)->reveal($room, ContentType::Phrase, $game->settings['packs'], 1, $game->settings['allow_repeats'] ?? false)->firstOrFail();
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
            'round' => ['content_id' => $content->id, 'prompt' => $content->payload['prompt'], 'participants' => $players,
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
        abort_unless($game !== null && $game->type === 'phrase' && $game->id === $gameId && $game->state['number'] === $round && $game->state['phase'] === $phase, 409);
        abort_unless($this->eligible($player, $game->state), 403);

        return $game;
    }

    public function write(Room $room, RoomPlayer $player, string $suffix, bool $submit, int $gameId, int $round): void
    {
        $game = $this->current($room, $player, $gameId, $round, 'writing');
        $state = $game->state;
        abort_if(isset($state['round']['submitted'][$player->id]), 409);
        $state['round']['drafts'][$player->id] = $suffix;
        if ($submit) {
            $state['round']['submitted'][$player->id] = true;
        }
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    public function vote(Room $room, RoomPlayer $player, string $choice, int $gameId, int $round): void
    {
        $game = $this->current($room, $player, $gameId, $round, 'voting');
        $state = $game->state;
        abort_if(isset($state['round']['votes'][$player->id]), 409);
        $entry = null;
        foreach ($state['round']['entries'] as $candidate) {
            if ($candidate['id'] === $choice) {
                $entry = $candidate;
                break;
            }
        }
        if ($entry === null) {
            throw ValidationException::withMessages(['choice' => __('rooms.phrase_invalid_vote')]);
        }
        abort_if($entry['author'] === $player->id, 403);
        $state['round']['votes'][$player->id] = $choice;
        app(GameEngine::class)->save($game, ['state' => $state]);
        $this->tick($room, $game);
    }

    /** @param array<string, mixed> $state */
    private function finishRound(Room $room, Game $game, array $state): void
    {
        foreach ($state['round']['entries'] as $entry) {
            $count = count(array_filter($state['round']['votes'], fn ($id) => $id === $entry['id']));
            $points = app(Scoring::class)->votes($count);
            $state['round']['awards'][$entry['author']] = $points;
            $state['scores'][$entry['author']] = ($state['scores'][$entry['author']] ?? 0) + $points;
            $room->players()->whereKey($entry['author'])->increment('score', $points);
        }
        $state['phase'] = 'reveal';
        $state['deadline'] = app(GameEngine::class)->time() + 3;
        app(GameEngine::class)->save($game, ['state' => $state]);
    }

    public function tick(Room $room, Game $game): Game
    {
        $state = $game->state;
        $time = app(GameEngine::class)->time();
        $players = $room->players()->get()->filter(fn ($player) => $this->eligible($player, $state));
        if ($state['phase'] === 'writing' && ($state['deadline'] <= $time || $players->every(fn ($player) => isset($state['round']['submitted'][$player->id])))) {
            $entries = [];
            foreach ($state['round']['drafts'] as $author => $suffix) {
                if (trim($suffix) !== '') {
                    $entries[] = ['id' => (string) Str::uuid(), 'author' => (int) $author, 'text' => $state['round']['prompt'].' '.app(GameBonuses::class)->phrase($game, (int) $author, $suffix)];
                }
            }
            shuffle($entries);
            $state['round']['entries'] = $entries;
            if ($entries === []) {
                $this->finishRound($room, $game, $state);

                return $game;
            }
            $state['phase'] = 'presenting';
            $state['deadline'] = $time + $this->displayDuration($entries[0]['text']);
            app(GameEngine::class)->save($game, ['state' => $state]);
        } elseif ($state['phase'] === 'presenting' && $state['deadline'] <= $time) {
            // Advance against the existing deadline, including scheduler catch-up.
            do {
                $state['round']['index']++;
                if ($state['round']['index'] >= count($state['round']['entries'])) {
                    $state['phase'] = 'voting';
                    $state['deadline'] += 30;
                    break;
                }
                $state['deadline'] += $this->displayDuration($state['round']['entries'][$state['round']['index']]['text']);
            } while ($state['deadline'] <= $time);
            app(GameEngine::class)->save($game, ['state' => $state]);
        } elseif ($state['phase'] === 'voting' && ($state['deadline'] <= $time || $players->filter(fn ($player) => array_filter($state['round']['entries'], fn ($entry) => $entry['author'] !== $player->id) !== [])->every(fn ($player) => isset($state['round']['votes'][$player->id])))) {
            $this->finishRound($room, $game, $state);
        } elseif ($state['phase'] === 'reveal' && $state['deadline'] <= $time) {
            $target = $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target;
            if ($state['number'] >= $game->settings['rounds'] || $target) {
                $state['phase'] = 'results';
                app(GameEngine::class)->save($game, ['state' => $state, 'status' => 'finished']);
            } else {
                $this->nextRound($room, $game);
            }
        }

        return $game;
    }

    public function displayDuration(string $text): float
    {
        return min(12.0, 5 + 0.05 * max(0, mb_strlen($text) - 40));
    }

    /** @return array<string, mixed> */
    public function view(Room $room, Game $game, ?RoomPlayer $me): array
    {
        $state = $game->state;
        $round = $state['round'];
        $phase = in_array($state['phase'], ['paused', 'resuming'], true) ? $state['previous_phase'] : $state['phase'];
        $revealed = in_array($phase, ['reveal', 'results'], true);
        $entries = $phase === 'writing' ? [] : ($phase === 'presenting' ? array_slice($round['entries'], $round['index'], 1) : $round['entries']);
        $own = null;
        foreach ($round['entries'] as $entry) {
            if ($entry['author'] === $me?->id) {
                $own = $entry;
                break;
            }
        }

        return ['id' => $game->id, 'type' => 'phrase', 'settings' => $game->settings, 'exhausted' => $state['exhausted'] ?? false,
            'targetReached' => $game->status === 'finished' && $room->point_target !== null && (int) $room->players()->max('score') >= $room->point_target,
            'phase' => $state['phase'], 'deadline' => $state['deadline'], 'scores' => $state['scores'],
            'round' => ['number' => $state['number'], 'total' => $game->settings['rounds'], 'prompt' => $round['prompt'],
                'entries' => array_map(fn ($entry) => ['id' => $entry['id'], 'text' => $entry['text'], ...($revealed ? ['author' => $entry['author'], 'votes' => count(array_filter($round['votes'], fn ($id) => $id === $entry['id'])), 'points' => $round['awards'][$entry['author']] ?? 0] : [])], $entries),
                'awards' => $revealed ? $round['awards'] : []],
            'me' => $me === null ? null : ['eligible' => $this->eligible($me, $state), 'draft' => $round['drafts'][$me->id] ?? '',
                'submitted' => isset($round['submitted'][$me->id]), 'ownEntry' => $own['id'] ?? null,
                'voted' => isset($round['votes'][$me->id]), 'choice' => $round['votes'][$me->id] ?? null,
                'points' => $revealed ? ($round['awards'][$me->id] ?? 0) : null]];
    }
}
