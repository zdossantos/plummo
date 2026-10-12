<?php

use App\Services\DrawingAnswer;

it('accepts common singular and plural forms in both directions', function (string $singular, string $plural) {
    $answers = new DrawingAnswer;
    expect($answers->matches($singular, $plural))->toBeTrue();
    expect($answers->matches($plural, $singular))->toBeTrue();
})->with([
    ['chat', 'chats'], ['cheval', 'chevaux'], ['animal', 'animaux'],
    ['bateau', 'bateaux'], ['bijou', 'bijoux'], ['œil', 'yeux'],
    ['pomme de terre', 'pommes de terre'], ['chat noir', 'chats noirs'],
    ['ÉLÉPHANT', 'éléphants'], ['élé-phant', 'ELEPHANT'],
    ['cat', 'cats'], ['signal', 'signals'], ['metal', 'metals'], ['canal', 'canals'], ['general', 'generals'], ['chatnoir', 'chats noirs'], ['bus', 'buses'], ['ele-phant', 'elephants'], ['chat noir', 'chatsnoirs'], ['coral', 'corals'], ['animal', 'animals'], ['glass', 'glasses'], ['gas', 'gases'], ['éléphant', 'ele-phants'], ['pomme de terre', 'pommesdeterre'], ['box', 'boxes'], ['baby', 'babies'],
    ['child', 'children'], ['mouse', 'mice'], ['foot', 'feet'],
]);

it('does not treat incomplete words or unrelated words as plurals', function (string $word, string $guess) {
    expect((new DrawingAnswer)->matches($word, $guess))->toBeFalse();
})->with([
    ['bus', 'bu'], ['box', 'boxe'], ['T-Rex', 'Ts-Rex'], ['chevaux', 'chevauxes'], ['bijoux', 'bijouxes'], ['bras', 'bra'], ['souris', 'souri'], ['noix', 'noi'],
    ['radis', 'radi'], ['sheep', 'sheeps'], ['cheval', 'chevals'],
    ['chat', 'chapeau'], ['éléphant 1', 'éléphant 2'], ['chats noirs', 'chat blanc'],
]);
