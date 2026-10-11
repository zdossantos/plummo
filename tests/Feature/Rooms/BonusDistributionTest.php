<?php

use App\Services\BonusCatalog;
use App\Services\BonusDistribution;

it('gives tied players the same neutral draw and leaders mostly protections', function () {
    $draw = app(BonusDistribution::class);
    $tied = $draw->weights('quiz', 1, [1 => 0, 2 => 0]);
    expect($tied)->toBe($draw->weights('quiz', 2, [1 => 0, 2 => 0]));
    $leader = $draw->weights('quiz', 1, [1 => 1000, 2 => 500, 3 => 0]);
    $protection = $leader['shield'] + $leader['soap'] + $leader['boomerang'];
    expect($protection / array_sum($leader))->toBeGreaterThan(0.6);
});

it('increases strong prank odds with the score deficit without removing variety', function () {
    $draw = app(BonusDistribution::class);
    $near = $draw->weights('quiz', 2, [1 => 1000, 2 => 900, 3 => 0]);
    $far = $draw->weights('quiz', 2, [1 => 1000, 2 => 100, 3 => 0]);
    expect($far['strike'] / $far['photobomb'])->toBeGreaterThan($near['strike'] / $near['photobomb']);
    expect(min($far))->toBeGreaterThan(0);
    expect(($far['shield'] + $far['soap'] + $far['boomerang']) / array_sum($far))->toBeLessThan(0.25);
});

it('keeps draws compatible and excludes the replaced item', function () {
    $draw = app(BonusDistribution::class);
    $weights = $draw->weights('phrase', 1, [1 => 0, 2 => 100], 'shield');
    expect($weights)->not->toHaveKeys(['shield', 'dice', 'paint']);
    for ($i = 0; $i < 100; $i++) {
        expect($draw->choose('phrase', 1, [1 => 0, 2 => 100], 'shield'))->toBeIn(array_keys($weights));
    }
});

it('rates every compatible object and preserves identical odds for tied leaders', function (string $type) {
    $draw = app(BonusDistribution::class);
    $leader = $draw->weights($type, 1, [1 => 100, 2 => 100, 3 => 0]);
    expect($leader)->toBe($draw->weights($type, 2, [1 => 100, 2 => 100, 3 => 0]));
    expect(array_sum($leader))->toBeGreaterThan(0.999)->toBeLessThan(1.001);
    expect(min($leader))->toBeGreaterThan(0);
    foreach (['shield', 'soap', 'boomerang'] as $kind) {
        expect(app(BonusCatalog::class)->disturbance($kind))->toBe(0);
    }
})->with(['quiz', 'blind_test', 'phrase', 'drawing']);
