<?php

use App\Services\BluffAnswers;
use Tests\TestCase;

uses(TestCase::class);

it('groups normalized bluffs and truth without exposing another truth card', function () {
    $entries = app(BluffAnswers::class)->build('Éléphant bleu', [1 => 'éléphant BLEU!', 2 => 'Un avion', 3 => 'un  avion.']);
    expect($entries)->toHaveCount(2);
    $truth = collect($entries)->firstWhere('correct', true);
    $bluff = collect($entries)->firstWhere('correct', false);
    expect($truth['authors'])->toBe([1])->and($bluff['authors'])->toBe([2, 3]);
    expect(array_unique(array_column($entries, 'id')))->toHaveCount(2);
});

it('awards 65 per vote to the correct voter or shared bluff authors', function () {
    $entries = [['id' => 'truth', 'correct' => true, 'authors' => [1]], ['id' => 'bluff', 'correct' => false, 'authors' => [2, 3]]];
    $awards = app(BluffAnswers::class)->awards($entries, [4 => 'truth', 5 => 'bluff', 6 => 'bluff']);
    expect($awards)->toBe([1 => 65, 4 => 65, 2 => 65, 3 => 65]);
});

it('does not reward abstentions and rounds a shared total once', function () {
    $entries = [['id' => 'truth', 'correct' => true, 'authors' => []], ['id' => 'bluff', 'correct' => false, 'authors' => [2, 3]]];
    expect(app(BluffAnswers::class)->awards($entries, []))->toBe([]);
    expect(app(BluffAnswers::class)->awards($entries, [1 => 'bluff']))->toBe([2 => 33, 3 => 33]);
});
