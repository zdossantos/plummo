<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Support\Str;

class GameBonuses
{
    /** @return list<string> */
    public function kinds(string $type): array
    {
        return match ($type) {
            'quiz' => ['bolt', 'dice', 'squatter'],
            'blind_test' => ['bolt', 'dice', 'squatter', 'artist'],
            'phrase' => ['accent', 'sneeze'],
            'drawing' => ['stamp', 'paint'],
            default => [],
        };
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function transition(Game $game, array $state, string $status): array
    {
        $old = $game->state;
        $bonus = $state['bonuses'] ?? $old['bonuses'] ?? ['enabled' => (bool) ($game->settings['bonuses'] ?? false) && count($state['round']['participants'] ?? []) > 1, 'inventory' => [], 'used' => [], 'effects' => [], 'receipts' => []];
        if ($status !== 'active') {
            $bonus['inventory'] = [];
            $bonus['pending'] = [];
            $bonus['effects'] = [];
        } elseif ($bonus['enabled'] && ($state['number'] ?? 0) > ($old['number'] ?? 0)) {
            $bonus['used'] = [];
            $bonus['effects'] = [];
            $best = max([0, ...array_values($state['scores'] ?? [])]);
            foreach ($state['round']['participants'] as $id) {
                if (! isset($bonus['inventory'][$id])) {
                    $bonus['inventory'][$id] = [];
                    $this->give($bonus, $id, $game->type);
                } elseif (($state['scores'][$id] ?? 0) < $best && ! isset($bonus['pending'][$id])) {
                    $this->give($bonus, $id, $game->type);
                }
            }
        }
        $now = app(GameEngine::class)->time();
        $wasFrozen = in_array($old['phase'] ?? '', ['paused', 'resuming'], true);
        $frozen = in_array($state['phase'] ?? '', ['paused', 'resuming'], true);
        foreach ($bonus['effects'] as &$effect) {
            if ($effect['expiresAt'] === null) {
                continue;
            }
            if ($frozen && ! $wasFrozen) {
                $effect['remaining'] = max(0, $effect['expiresAt'] - $now);
                $effect['elapsed'] = max(0, $now - $effect['startedAt']);
            } elseif (! $frozen && $wasFrozen && isset($effect['remaining'])) {
                $effect['expiresAt'] = $now + $effect['remaining'];
                $effect['startedAt'] = $now - $effect['elapsed'];
                unset($effect['remaining'], $effect['elapsed']);
            }
        }
        unset($effect);
        if (! $frozen) {
            $bonus['effects'] = array_values(array_filter($bonus['effects'], fn ($effect) => $effect['expiresAt'] === null || $effect['expiresAt'] > $now));
        }
        $bonus['receipts'] = array_values(array_filter($bonus['receipts'], fn ($receipt) => $receipt['at'] > $now - 8));
        $bonus['launches'] = array_values(array_filter($bonus['launches'] ?? [], fn ($event) => $event['at'] > $now - 8));
        $state['bonuses'] = $bonus;

        return $state;
    }

    /** @param array<string, mixed> $bonus */
    private function give(array &$bonus, int $id, string $type): void
    {
        $kinds = $this->kinds($type);
        $item = ['id' => (string) Str::uuid(), 'kind' => $kinds[random_int(0, count($kinds) - 1)]];
        if (count($bonus['inventory'][$id]) < 2) {
            $bonus['inventory'][$id][] = $item;
        } else {
            $bonus['pending'][$id] = $item;
        }
        $bonus['receipts'][] = [...$item, 'recipientId' => $id, 'at' => app(GameEngine::class)->time()];
    }

    public function use(Room $room, RoomPlayer $player, int $gameId, int $round, string $itemId): void
    {
        $engine = app(GameEngine::class);
        $game = $engine->tick($room);
        abort_unless($game !== null && $game->id === $gameId && $game->state['number'] === $round, 409);
        $state = $game->state;
        $phase = match ($game->type) {
            'phrase' => 'writing', 'drawing' => 'drawing', default => 'answer',
        };
        abort_unless($state['phase'] === $phase && ($state['bonuses']['enabled'] ?? false), 409);
        abort_unless($player->connected() && in_array($player->id, $state['round']['participants'], true) && ! in_array($player->id, $state['excluded'] ?? [], true), 403);
        $bonus = $state['bonuses'];
        abort_if(in_array($player->id, $bonus['used'], true), 409);
        $items = $bonus['inventory'][$player->id] ?? [];
        $index = array_search($itemId, array_column($items, 'id'), true);
        abort_if($index === false, 409);
        $kind = $items[$index]['kind'];
        abort_unless(in_array($kind, $this->kinds($game->type), true), 409);
        $targets = $room->players()->get()->filter(fn (RoomPlayer $other) => $other->id !== $player->id && $other->connected() && in_array($other->id, $state['round']['participants'], true) && ! in_array($other->id, $state['excluded'] ?? [], true))->modelKeys();
        abort_if($targets === [], 409);
        array_splice($items, $index, 1);
        if (isset($bonus['pending'][$player->id])) {
            $items[] = $bonus['pending'][$player->id];
            unset($bonus['pending'][$player->id]);
        }
        $bonus['inventory'][$player->id] = $items;
        $bonus['used'][] = $player->id;
        $now = $engine->time();
        $bonus['launches'][] = ['id' => (string) Str::uuid(), 'kind' => $kind, 'actorId' => $player->id, 'at' => $now];
        $duration = match ($kind) {
            'bolt' => 3, 'dice' => 6, 'accent', 'sneeze' => null, default => 4
        };
        // Each recipient gets its own clock: another launch never extends an active effect.
        foreach (in_array($kind, ['stamp', 'paint'], true) ? [0] : $targets as $target) {
            $exists = false;
            foreach ($bonus['effects'] as $effect) {
                if ($effect['kind'] === $kind && $effect['target'] === $target && ($effect['expiresAt'] === null || $effect['expiresAt'] > $now)) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $bonus['effects'][] = ['id' => (string) Str::uuid(), 'kind' => $kind, 'target' => $target, 'actorId' => $player->id, 'startedAt' => $now, 'expiresAt' => $duration === null ? null : $now + $duration];
            }
        }
        $state['bonuses'] = $bonus;
        $engine->save($game, ['state' => $state]);
    }

    /** @return array<string, mixed> */
    public function view(Game $game, ?RoomPlayer $me, bool $screen): array
    {
        $bonus = $game->state['bonuses'] ?? [];
        $phase = match ($game->type) {
            'phrase' => 'writing', 'drawing' => 'drawing', default => 'answer'
        };
        $playing = $game->state['phase'] === $phase && $game->status === 'active';
        $effects = $playing ? array_values(array_filter($bonus['effects'] ?? [], fn ($effect) => ($effect['expiresAt'] === null || $effect['expiresAt'] > app(GameEngine::class)->time()) && ($effect['target'] === 0 || (! $screen && $effect['target'] === $me?->id)))) : [];
        $effects = array_map(function ($effect) {
            unset($effect['target']);

            return $effect;
        }, $effects);

        return ['pending' => $screen || $me === null ? null : ($bonus['pending'][$me->id] ?? null), 'launches' => array_values(array_filter($bonus['launches'] ?? [], fn ($event) => $event['at'] > app(GameEngine::class)->time() - 8)), 'enabled' => $bonus['enabled'] ?? false, 'inventory' => $screen || $me === null ? [] : ($bonus['inventory'][$me->id] ?? []), 'canUse' => $playing && $me?->connected() && in_array($me->id, $game->state['round']['participants'], true) && ! in_array($me->id, $game->state['excluded'] ?? [], true) && ! in_array($me->id, $bonus['used'] ?? [], true), 'effects' => $effects, 'receipts' => array_values(array_filter($bonus['receipts'] ?? [], fn ($receipt) => $receipt['at'] > app(GameEngine::class)->time() - 8 && ($screen || $receipt['recipientId'] === $me?->id)))];
    }

    public function replace(Room $room, RoomPlayer $player, int $gameId, int $round, string $itemId, ?string $replaceId): void
    {
        $engine = app(GameEngine::class);
        $game = $engine->tick($room);
        abort_unless($game !== null && $game->id === $gameId && $game->status === 'active' && $game->state['number'] === $round, 409);
        abort_unless($player->connected() && in_array($player->id, $game->state['round']['participants'], true) && ! in_array($player->id, $game->state['excluded'] ?? [], true), 403);
        $state = $game->state;
        $bonus = $state['bonuses'];
        $pending = $bonus['pending'][$player->id] ?? null;
        abort_unless($pending !== null && $pending['id'] === $itemId && in_array($pending['kind'], $this->kinds($game->type), true), 409);
        if ($replaceId !== null) {
            $index = array_search($replaceId, array_column($bonus['inventory'][$player->id], 'id'), true);
            abort_if($index === false, 409);
            $bonus['inventory'][$player->id][$index] = $pending;
        }
        unset($bonus['pending'][$player->id]);
        $state['bonuses'] = $bonus;
        $engine->save($game, ['state' => $state]);
    }

    public function paint(Game $game, string $color): string
    {
        foreach ($game->state['bonuses']['effects'] ?? [] as $effect) {
            if ($effect['kind'] === 'paint' && $effect['expiresAt'] > app(GameEngine::class)->time()) {
                return '#f05a78';
            }
        }

        return $color;
    }

    public function phrase(Game $game, int $author, string $suffix): string
    {
        $effects = $game->state['bonuses']['effects'] ?? [];
        $kinds = array_column(array_filter($effects, fn ($effect) => $effect['target'] === $author), 'kind');
        if (in_array('accent', $kinds, true)) {
            $suffix = strtr($suffix, ['r' => 'w', 'R' => 'W']);
        }
        if (in_array('sneeze', $kinds, true)) {
            $words = preg_split('/\s+/u', trim($suffix), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            array_splice($words, (int) ceil(count($words) / 2), 0, ['ATCHOUM !']);
            $suffix = implode(' ', $words);
        }

        return $suffix;
    }
}
