<?php

use App\Services\Scoring;

it('awards the agreed integer points for one to eight participants', function (array $points) {
    $scoring = new Scoring;
    foreach ($points as $index => $expected) {
        expect($scoring->rapid(count($points), $index + 1))->toBe($expected);
    }
})->with([
    [[65]], [[100, 30]], [[100, 65, 30]], [[100, 77, 53, 30]],
    [[100, 83, 65, 48, 30]], [[100, 86, 72, 58, 44, 30]],
    [[100, 88, 77, 65, 53, 42, 30]], [[100, 90, 80, 70, 60, 50, 40, 30]],
]);

it('shares the occupied places for exact ties with half-up rounding', function () {
    $scoring = new Scoring;
    expect($scoring->rapid(8, 1, 2))->toBe(95)
        ->and($scoring->rapid(8, 3))->toBe(80)
        ->and($scoring->rapid(4, 1, 2))->toBe(89)
        ->and($scoring->rapid(4, 1, 4))->toBe(65);
});

it('rewards the artist proportionally and only distributes votes received', function () {
    $scoring = new Scoring;
    expect($scoring->artist(1, 1))->toBe(65)
        ->and($scoring->artist(4, 2))->toBe(33)
        ->and($scoring->artist(7, 0))->toBe(0)
        ->and($scoring->votes(0))->toBe(0)
        ->and($scoring->votes(7))->toBe(455);
});

it('rejects impossible participants ranks and vote counts', function () {
    $scoring = new Scoring;
    foreach ([[0, 1, 1], [9, 1, 1], [4, 0, 1], [4, 5, 1], [4, 3, 3], [4, 1, 0]] as [$n, $r, $tie]) {
        expect(fn () => $scoring->rapid($n, $r, $tie))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => $scoring->artist(0, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $scoring->artist(3, 4))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $scoring->votes(-1))->toThrow(InvalidArgumentException::class);
});
