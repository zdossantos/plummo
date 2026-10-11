<?php

namespace App\Services;

class BonusCatalog
{
    /** @return list<string> */
    public function kinds(string $type): array
    {
        $common = ['shield', 'soap', 'boomerang', 'pinata', 'washer', 'lock', 'mystery', 'bubbles', 'photobomb', 'jelly', 'strike', 'leak', 'freeze', 'extension', 'watch', 'overtime', 'last_breath', 'alarm', 'borrow'];

        return match ($type) {
            'quiz' => [...$common, 'bolt', 'dice', 'squatter', 'missing_word', 'alphabet', 'curtain'],
            'blind_test' => [...$common, 'bolt', 'dice', 'squatter', 'artist', 'curtain', 'scratch', 'speed', 'singer'],
            'phrase' => [...$common, 'bolt', 'accent', 'sneeze', 'translator', 'advert'],
            'drawing' => [...$common, 'bolt', 'stamp', 'paint', 'giant', 'mirror', 'clumsy'],
            default => [],
        };
    }

    /** Internal disturbance score: protections and harmless gifts are zero. */
    public function disturbance(string $kind): int
    {
        return match ($kind) {
            'shield', 'soap', 'boomerang', 'pinata', 'freeze', 'extension', 'last_breath' => 0,
            'mystery', 'bubbles', 'photobomb', 'borrow' => 1,
            'squatter', 'artist', 'stamp', 'singer', 'overtime' => 2,
            'bolt', 'paint', 'jelly', 'accent', 'sneeze', 'advert', 'scratch', 'mirror' => 3,
            'dice', 'washer', 'watch', 'leak', 'alarm', 'translator', 'alphabet', 'giant', 'clumsy', 'speed' => 4,
            'lock', 'strike', 'missing_word', 'curtain' => 5,
            default => throw new \InvalidArgumentException('Unknown bonus kind: '.$kind),
        };
    }

    public function defense(string $kind): bool
    {
        return in_array($kind, ['shield', 'soap', 'boomerang'], true);
    }

    public function shared(string $kind): bool
    {
        return in_array($kind, ['leak', 'freeze', 'extension', 'overtime', 'last_breath', 'borrow', 'scratch', 'speed', 'singer', 'mirror'], true);
    }

    public function duration(string $kind, int $duration): ?int
    {
        return match ($kind) {
            'shield', 'boomerang', 'accent', 'sneeze', 'translator', 'advert', 'last_breath' => null,
            'bolt' => 3,
            'dice' => 6,
            'stamp', 'paint' => 4,
            'photobomb', 'scratch', 'singer', 'squatter', 'artist' => 4,
            'speed' => 8,
            'alarm' => 12,
            default => min(20, max(8, (int) round($duration * 0.2))),
        };
    }

    public function phase(string $type): string
    {
        return match ($type) {
            'phrase' => 'writing', 'drawing' => 'drawing', default => 'answer',
        };
    }
}
