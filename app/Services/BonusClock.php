<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Str;

class BonusClock
{
    public function step(Game $game): int
    {
        return min(20, max(6, (int) round(($game->type === 'tribunal' ? 20 : $game->settings['duration']) * 0.2)));
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $bonus
     * @return array<string, mixed>
     */
    public function apply(Game $game, array $state, array &$bonus, string $kind, int $actor): array
    {
        $now = app(GameEngine::class)->time();
        $step = $this->step($game);
        $bonus['clock'] ??= [];
        if ($kind === 'last_breath') {
            $bonus['clock']['lastActor'] ??= $actor;
        } elseif (in_array($kind, ['leak', 'extension', 'freeze', 'overtime', 'borrow'], true)) {
            $delta = $this->change($game, $state, $bonus, $kind === 'leak' ? -$step : $step);
            if ($kind === 'freeze' && $delta > 0) {
                if (($bonus['clock']['frozenUntil'] ?? 0) > $now) {
                    $bonus['clock']['frozenUntil'] += $delta;
                } else {
                    $bonus['clock']['frozenUntil'] = $now + $delta;
                    $bonus['clock']['frozenValue'] = max(0, $state['deadline'] - $now - $delta);
                }
            } elseif (($bonus['clock']['frozenUntil'] ?? 0) > $now) {
                $bonus['clock']['frozenValue'] = max(0, $bonus['clock']['frozenValue'] + $delta);
            }
            if ($kind === 'borrow') {
                $bonus['debt'] = ($bonus['debt'] ?? 0) + $delta;
            }
            if ($kind === 'overtime') {
                foreach ($bonus['effects'] as &$effect) {
                    if ($effect['expiresAt'] !== null && $effect['expiresAt'] > $now) {
                        $effect['expiresAt'] += min(6, $step / 2);
                    }
                }
                unset($effect);
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $bonus
     */
    private function change(Game $game, array &$state, array &$bonus, float $requested): float
    {
        $now = app(GameEngine::class)->time();
        $key = $requested < 0 ? 'removed' : 'added';
        $cap = min(30, ($game->type === 'tribunal' ? 20 : $game->settings['duration']) * 0.5);
        $delta = min(abs($requested), max(0, $cap - ($bonus['clock'][$key] ?? 0)));
        if ($requested < 0) {
            $delta = -min($delta, max(0, $state['deadline'] - $now - 8));
        }
        $state['deadline'] += $delta;
        $bonus['clock'][$key] = ($bonus['clock'][$key] ?? 0) + abs($delta);

        return $delta;
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $bonus
     * @return array<string, mixed>
     */
    public function start(Game $game, array $state, array &$bonus): array
    {
        $bonus['clock'] = [];
        if (($bonus['debt'] ?? 0) > 0) {
            $this->change($game, $state, $bonus, -$bonus['debt']);
            $bonus['debt'] = 0;
        }

        return $state;
    }

    public function tick(Game $game): void
    {
        $state = $game->state;
        if ($state['phase'] !== app(BonusCatalog::class)->phase($game->type)) {
            return;
        }
        $bonus = $state['bonuses'] ?? [];
        $clock = $bonus['clock'] ?? [];
        $now = app(GameEngine::class)->time();
        if (isset($clock['lastActor']) && ! ($clock['lastUsed'] ?? false) && $state['deadline'] - $now <= 2) {
            $bonus['clock']['lastUsed'] = true;
            $state['deadline'] = max($now, $state['deadline']);
            $this->change($game, $state, $bonus, $this->step($game));
            $bonus['launches'][] = ['id' => (string) Str::uuid(), 'kind' => 'last_breath', 'actorId' => $clock['lastActor'], 'at' => $now, 'reaction' => true];
            $state['bonuses'] = $bonus;
            app(GameEngine::class)->save($game, ['state' => $state]);
        }
    }
}
