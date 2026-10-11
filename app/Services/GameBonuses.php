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
        return app(BonusCatalog::class)->kinds($type);
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function transition(Game $game, array $state, string $status): array
    {
        $old = $game->state;
        $bonus = $state['bonuses'] ?? $old['bonuses'] ?? ['enabled' => (bool) ($game->settings['bonuses'] ?? false) && count($state['round']['participants'] ?? []) > 1, 'inventory' => [], 'used' => [], 'defenses' => [], 'effects' => [], 'receipts' => []];
        if ($status !== 'active') {
            $bonus['inventory'] = [];
            $bonus['pending'] = [];
            $bonus['effects'] = [];
        } elseif ($bonus['enabled'] && ($state['number'] ?? 0) > ($old['number'] ?? 0)) {
            $bonus['used'] = [];
            $bonus['effects'] = [];
            $bonus['defenses'] = [];
            $best = max([0, ...array_values($state['scores'] ?? [])]);
            foreach ($state['round']['participants'] as $id) {
                $bonus['inventory'][$id] ??= [];
                $this->give($bonus, $id, $game->type);
                if (($state['scores'][$id] ?? 0) < $best) {
                    $this->give($bonus, $id, $game->type);
                }
            }
        }
        if ($status === 'active' && $state['phase'] === app(BonusCatalog::class)->phase($game->type) && (($state['number'] ?? 0) > ($old['number'] ?? 0) || ($old['phase'] ?? '') === 'selecting')) {
            $state = app(BonusClock::class)->start($game, $state, $bonus);
        }
        $now = app(GameEngine::class)->time();
        $wasFrozen = in_array($old['phase'] ?? '', ['paused', 'resuming', 'artist_missing'], true);
        $frozen = in_array($state['phase'] ?? '', ['paused', 'resuming', 'artist_missing'], true);
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
        if ($frozen && ! $wasFrozen && isset($bonus['clock']['frozenUntil'])) {
            $bonus['clock']['freezeRemaining'] = max(0, $bonus['clock']['frozenUntil'] - $now);
        } elseif (! $frozen && $wasFrozen && isset($bonus['clock']['freezeRemaining'])) {
            $bonus['clock']['frozenUntil'] = $now + $bonus['clock']['freezeRemaining'];
            unset($bonus['clock']['freezeRemaining']);
        }
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
        if (isset($bonus['pending'][$id])) {
            return;
        }
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
        $items = $bonus['inventory'][$player->id] ?? [];
        $index = array_search($itemId, array_column($items, 'id'), true);
        abort_if($index === false, 409);
        $kind = $items[$index]['kind'];
        abort_unless(in_array($kind, $this->kinds($game->type), true), 409);
        $catalog = app(BonusCatalog::class);
        abort_unless($this->available($game, $player, $kind), 409);
        $targets = $room->players()->get()->filter(fn (RoomPlayer $other) => $other->id !== $player->id && $other->connected() && in_array($other->id, $state['round']['participants'], true) && ! in_array($other->id, $state['excluded'] ?? [], true))->modelKeys();
        abort_if($targets === [], 409);
        array_splice($items, $index, 1);
        if (isset($bonus['pending'][$player->id])) {
            $items[] = $bonus['pending'][$player->id];
            unset($bonus['pending'][$player->id]);
        }
        $bonus['inventory'][$player->id] = $items;
        if ($catalog->defense($kind)) {
            $bonus['defenses'][] = $player->id;
        } else {
            $bonus['used'][] = $player->id;
        }
        $now = $engine->time();
        $bonus['launches'][] = ['id' => (string) Str::uuid(), 'kind' => $kind, 'actorId' => $player->id, 'at' => $now];
        $duration = $catalog->duration($kind, $game->settings['duration']);
        if ($kind === 'soap') {
            $bonus['effects'] = array_values(array_filter($bonus['effects'], fn ($effect) => $effect['target'] !== $player->id || $catalog->defense($effect['kind'])));
        } elseif ($kind === 'pinata') {
            foreach ([$player->id, ...$targets] as $id) {
                $this->give($bonus, $id, $game->type);
            }
        } elseif ($catalog->defense($kind)) {
            $this->effect($bonus, $kind, $player->id, $player->id, $duration);
        } elseif ($catalog->shared($kind)) {
            $state = app(BonusClock::class)->apply($game, $state, $bonus, $kind, $player->id);
            $this->effect($bonus, $kind, 0, $player->id, $duration);
            foreach ($bonus['effects'] as &$effect) {
                if ($effect['kind'] === 'freeze' && $effect['target'] === 0) {
                    $effect['expiresAt'] = $bonus['clock']['frozenUntil'] ?? $now;
                }
            }
            unset($effect);
        } else {
            foreach ($targets as $target) {
                if (in_array($kind, ['stamp', 'paint', 'giant', 'clumsy'], true) && $target !== ($state['round']['artist'] ?? null)) {
                    continue;
                }
                $recipient = $this->recipient($bonus, $target, $player->id, in_array($kind, ['stamp', 'paint', 'giant', 'clumsy'], true));
                if ($recipient === null) {
                    continue;
                }
                if ($kind === 'washer') {
                    foreach ($bonus['inventory'][$recipient] ?? [] as $index => $item) {
                        $kinds = array_values(array_diff($this->kinds($game->type), [$item['kind']]));
                        $new = ['id' => (string) Str::uuid(), 'kind' => $kinds[random_int(0, count($kinds) - 1)]];
                        $bonus['inventory'][$recipient][$index] = $new;
                        $bonus['receipts'][] = [...$new, 'recipientId' => $recipient, 'at' => $now];
                    }
                } elseif ($kind === 'alarm') {
                    foreach ($bonus['effects'] as &$effect) {
                        if ($effect['target'] === $recipient && $effect['expiresAt'] !== null && ! $catalog->defense($effect['kind'])) {
                            $effect['expiresAt'] += min(12, max(0, $effect['expiresAt'] - $now));
                        }
                    }
                    unset($effect);
                    $this->effect($bonus, $kind, $recipient, $player->id, $duration);
                } else {
                    $this->effect($bonus, $kind, $recipient, $player->id, $duration);
                }
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
        $effects = $playing ? array_values(array_filter($bonus['effects'] ?? [], fn ($effect) => ($effect['expiresAt'] === null || $effect['expiresAt'] > app(GameEngine::class)->time()) && ($effect['target'] === 0 || (($screen || $me === null) && $game->type === 'drawing' && $effect['target'] === ($game->state['round']['artist'] ?? null) && in_array($effect['kind'], ['stamp', 'paint', 'giant', 'clumsy'], true)) || (! $screen && $effect['target'] === $me?->id)))) : [];
        $effects = array_map(function ($effect) {
            unset($effect['target']);

            return $effect;
        }, $effects);

        return ['clock' => $playing && ($bonus['clock']['frozenUntil'] ?? 0) > app(GameEngine::class)->time() ? ['frozenValue' => (int) ceil($bonus['clock']['frozenValue'])] : null, 'pending' => $screen || $me === null ? null : ($bonus['pending'][$me->id] ?? null), 'launches' => array_values(array_filter($bonus['launches'] ?? [], fn ($event) => $event['at'] > app(GameEngine::class)->time() - 8)), 'enabled' => $bonus['enabled'] ?? false, 'inventory' => $screen || $me === null ? [] : array_map(fn ($item) => [...$item, 'canUse' => $this->available($game, $me, $item['kind'])], $bonus['inventory'][$me->id] ?? []), 'canUse' => $playing && $me?->connected() && in_array($me->id, $game->state['round']['participants'], true) && ! in_array($me->id, $game->state['excluded'] ?? [], true), 'effects' => $effects, 'receipts' => array_values(array_filter($bonus['receipts'] ?? [], fn ($receipt) => $receipt['at'] > app(GameEngine::class)->time() - 8 && ($screen || $receipt['recipientId'] === $me?->id)))];
    }

    public function available(Game $game, RoomPlayer $player, string $kind): bool
    {
        $state = $game->state;
        $bonus = $state['bonuses'] ?? [];
        $catalog = app(BonusCatalog::class);
        if (! ($bonus['enabled'] ?? false) || $state['phase'] !== $catalog->phase($game->type) || ! $player->connected() || ! in_array($player->id, $state['round']['participants'], true) || in_array($player->id, $state['excluded'] ?? [], true)) {
            return false;
        }
        if ($catalog->defense($kind)) {
            return count(array_filter($bonus['defenses'] ?? [], fn ($id) => $id === $player->id)) < 2;
        }
        if (in_array($player->id, $bonus['used'] ?? [], true) || $this->has($bonus, $player->id, 'lock') || $this->has($bonus, $player->id, 'strike')) {
            return false;
        }
        if (in_array($kind, ['stamp', 'paint', 'giant', 'clumsy'], true) && ($state['round']['artist'] ?? null) === $player->id) {
            return false;
        }
        if ($kind === 'borrow' && ($game->type === 'drawing' ? ($state['tour'] >= $game->settings['rounds'] && ($state['queue'] ?? []) === []) : $state['number'] >= $game->settings['rounds'])) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $bonus */
    private function has(array $bonus, int $target, string $kind): bool
    {
        foreach ($bonus['effects'] ?? [] as $effect) {
            if ($effect['target'] === $target && $effect['kind'] === $kind && ($effect['expiresAt'] === null || $effect['expiresAt'] > app(GameEngine::class)->time())) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $bonus */
    private function recipient(array &$bonus, int $target, int $sender, bool $artistOnly = false): ?int
    {
        foreach (['shield', 'boomerang'] as $kind) {
            foreach ($bonus['effects'] as $index => $effect) {
                if ($effect['target'] === $target && $effect['kind'] === $kind) {
                    array_splice($bonus['effects'], $index, 1);
                    $bonus['launches'][] = ['id' => (string) Str::uuid(), 'kind' => $kind, 'actorId' => $target, 'at' => app(GameEngine::class)->time(), 'reaction' => true, 'deflected' => $artistOnly && $kind === 'boomerang'];

                    return $kind === 'shield' || $artistOnly ? null : $sender;
                }
            }
        }

        return $target;
    }

    /** @param array<string, mixed> $bonus */
    private function effect(array &$bonus, string $kind, int $target, int $sender, ?int $duration): void
    {
        if ($this->has($bonus, $target, $kind)) {
            return;
        }
        $now = app(GameEngine::class)->time();
        if ($duration !== null && $this->has($bonus, $target, 'alarm')) {
            $duration += min(12, $duration);
        }
        $bonus['effects'][] = ['id' => (string) Str::uuid(), 'kind' => $kind, 'target' => $target, 'actorId' => $sender, 'startedAt' => $now, 'expiresAt' => $duration === null ? null : $now + $duration];
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

    public function drawingEffect(Game $game, string $kind): bool
    {
        return $this->has($game->state['bonuses'] ?? [], $game->state['round']['artist'], $kind);
    }

    /** @param list<array{float, float}> $points
     * @return list<array{float, float}>
     */
    public function strokePoints(array $points, bool $clumsy): array
    {
        return $clumsy ? array_map(fn ($p) => [min(1, max(0, $p[0] + 0.008 * sin($p[1] * 90))), min(1, max(0, $p[1] + 0.012 * sin($p[0] * 70)))], $points) : $points;
    }

    public function paint(Game $game, string $color): string
    {
        foreach ($game->state['bonuses']['effects'] ?? [] as $effect) {
            if ($effect['kind'] === 'paint' && in_array($effect['target'], [0, $game->state['round']['artist']], true) && $effect['expiresAt'] > app(GameEngine::class)->time()) {
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

        if (in_array('translator', $kinds, true)) {
            $suffix = preg_replace_callback('/\b(manger|maison|ami|amis|argent|voiture|chat|chien|dormir|beau|belle|travail|eat|house|friend|friends|money|car|cat|dog|sleep|beautiful|work)\b/iu', fn ($m) => __('rooms.bonus_word_'.(['eat' => 'manger', 'house' => 'maison', 'friend' => 'ami', 'friends' => 'amis', 'money' => 'argent', 'car' => 'voiture', 'cat' => 'chat', 'dog' => 'chien', 'sleep' => 'dormir', 'beautiful' => 'beau', 'work' => 'travail'][mb_strtolower($m[0])] ?? mb_strtolower($m[0]))), $suffix) ?? $suffix;
        }
        if (in_array('advert', $kinds, true)) {
            $suffix .= ' — '.__('rooms.bonus_sponsor');
        }

        return $suffix;
    }
}
