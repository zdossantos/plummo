<?php

namespace App\Services;

use InvalidArgumentException;

final class Scoring
{
    public function rapid(int $participants, int $rank, int $tied = 1): int
    {
        if ($participants < 1 || $participants > 8 || $rank < 1 || $tied < 1 || $rank + $tied - 1 > $participants) {
            throw new InvalidArgumentException('Invalid participant count or occupied ranks.');
        }
        $sum = 0;
        for ($place = $rank; $place < $rank + $tied; $place++) {
            $sum += (int) round(65 + 35 * ($participants + 1 - 2 * $place) / max(1, $participants - 1), 0, PHP_ROUND_HALF_UP);
        }

        return (int) round($sum / $tied, 0, PHP_ROUND_HALF_UP);
    }

    public function artist(int $guessers, int $found): int
    {
        if ($guessers < 1 || $guessers > 7 || $found < 0 || $found > $guessers) {
            throw new InvalidArgumentException('A drawing requires at least one guesser.');
        }

        return (int) round(65 * $found / $guessers, 0, PHP_ROUND_HALF_UP);
    }

    public function votes(int $count): int
    {
        if ($count < 0 || $count > 7) {
            throw new InvalidArgumentException('Invalid number of votes received.');
        }

        return 65 * $count;
    }
}
