<?php

namespace App\Services;

class BonusDistribution
{
    /** @param array<int, int> $scores
     * @return array<string, float>
     */
    public function weights(string $type, int $id, array $scores, ?string $exclude = null): array
    {
        $catalog = app(BonusCatalog::class);
        $kinds = array_values(array_diff($catalog->kinds($type), [$exclude]));
        $best = max([0, ...array_values($scores)]);
        $score = $scores[$id] ?? 0;
        $tied = count(array_unique($scores)) <= 1;
        $behind = $best > 0 ? min(1, max(0, ($best - $score) / $best)) : 0;
        $defenseChance = $tied ? 0.25 : ($score >= $best ? 0.65 : 0.25 - 0.15 * $behind);
        $desired = $tied ? 2.5 : ($score >= $best ? 1.0 : 2.0 + 3.0 * $behind);
        $raw = [];
        $totals = [0.0, 0.0];
        foreach ($kinds as $kind) {
            $defense = (int) $catalog->defense($kind);
            $raw[$kind] = $defense ? 1.0 : max(0.02, exp(-(($catalog->disturbance($kind) - $desired) ** 2) / 2));
            $totals[$defense] += $raw[$kind];
        }
        $weights = [];
        foreach ($raw as $kind => $weight) {
            $defense = (int) $catalog->defense($kind);
            $weights[$kind] = $weight / $totals[$defense] * ($defense ? $defenseChance : 1 - $defenseChance);
        }

        return $weights;
    }

    /** @param array<int, int> $scores */
    public function choose(string $type, int $id, array $scores, ?string $exclude = null): string
    {
        $weights = $this->weights($type, $id, $scores, $exclude);
        if ($weights === []) {
            throw new \InvalidArgumentException('No compatible bonus available.');
        }
        $ticket = random_int(1, 1000000) / 1000000 * array_sum($weights);
        foreach ($weights as $kind => $weight) {
            $ticket -= $weight;
            if ($ticket <= 0) {
                return $kind;
            }
        }

        return array_key_last($weights);
    }
}
