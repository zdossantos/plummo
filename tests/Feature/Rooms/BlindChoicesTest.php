<?php

use App\Models\Tag;
use App\Services\BlindChoices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

it('prefers every shared song tag and keeps eight distinct title artist choices', function () {
    $early = Tag::create(['name' => '90s']);
    $late = Tag::create(['name' => '2000s']);
    $correct = blindSong('Correct', [$early->id, $late->id]);
    foreach (range(1, 7) as $number) {
        blindSong('Related '.$number, [$number % 2 ? $early->id : $late->id]);
    }
    blindSong('Unrelated');
    blindSong('Correct');
    blindSong('Hidden', [$early->id], false);
    $result = app(BlindChoices::class)->prepare($correct);
    expect($result['choices'])->toHaveCount(8)
        ->and(array_unique($result['choices']))->toHaveCount(8)
        ->and($result['choices'][$result['correct']])->toBe('Correct — Artist')
        ->and($result['choices'])->not->toContain('Unrelated — Artist', 'Hidden — Artist');
    expect(app(BlindChoices::class)->count())->toBe(9);
});

it('fills missing related choices from the global published catalogue', function () {
    $tag = Tag::create(['name' => 'Tag']);
    $correct = blindSong('Correct', [$tag->id]);
    blindSong('Related', [$tag->id]);
    foreach (range(1, 6) as $number) {
        blindSong('Other '.$number);
    }
    $result = app(BlindChoices::class)->prepare($correct);
    expect($result['choices'])->toHaveCount(8)->toContain('Related — Artist', 'Other 6 — Artist');
});

it('refuses fewer than eight distinct published title artist pairs', function () {
    $correct = blindSong('Correct');
    foreach (range(1, 6) as $number) {
        blindSong('Other '.$number);
    }
    blindSong(' Correct ');
    blindSong('Hidden', [], false);
    expect(app(BlindChoices::class)->count())->toBe(7);
    expect(fn () => app(BlindChoices::class)->prepare($correct))->toThrow(ValidationException::class);
});
