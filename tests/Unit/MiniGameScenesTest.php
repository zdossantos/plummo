<?php

use App\Services\MiniGameScenes;
use Tests\TestCase;

uses(TestCase::class);

it('varies cup speed without overlapping moves or hiding the final position', function () {
    foreach (range(1, 20) as $_) {
        $trial = app(MiniGameScenes::class)->generate('cups');
        $swaps = $trial['scene']['swaps'];
        expect(count(array_unique(array_column($swaps, 'duration'))))->toBeGreaterThan(2);
        $end = 0;
        $order = [0, 1, 2];
        foreach ($swaps as $swap) {
            expect($swap['at'])->toBeGreaterThanOrEqual($end);
            expect($swap['duration'])->toBeGreaterThanOrEqual(.32)->toBeLessThanOrEqual(.8);
            $end = $swap['at'] + $swap['duration'];
            [$a, $b] = $swap['positions'];
            [$order[$a], $order[$b]] = [$order[$b], $order[$a]];
        }
        expect($trial['observe'])->toBeGreaterThan($end);
        expect($order[$trial['correct']])->toBe(0);
    }
});

it('sends count characters from both sides while preserving the house population', function () {
    foreach (range(1, 20) as $_) {
        $trial = app(MiniGameScenes::class)->generate('count');
        $events = $trial['scene']['events'];
        expect(array_unique(array_column($events, 'side')))->toContain(-1, 1);
        $inside = 0;
        foreach ($events as $event) {
            $inside += $event['direction'];
            expect($inside)->toBeGreaterThanOrEqual(0);
        }
        expect($trial['correct'])->toBe($inside);
        expect($trial['scene']['residents'])->toHaveCount($inside);
    }
});
