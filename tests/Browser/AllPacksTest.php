<?php

use App\Models\Content;
use App\Models\Pack;

it('lets the chief launch from a phone without selecting a pack', function () {
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $room = openRoom();
    test()->withVite();
    try {
        $chief = visit('/join/'.$room->code)->on()->mobile()->withLocale('fr-FR')->fill('player-name', 'Camille')->click('Continuer')->click('Entrer dans le salon');
        $chief->assertSee('Tous les packs')->click('[data-choose-packs]')->assertSee('Sans sélection, tous les packs sont utilisés.');
        $chief->click('[data-slot="drawer-content"] button:has-text("Fermer")')->fill('game-rounds', '5');
        $chief->assertEnabled('[data-ready]')->assertAttribute('.readiness-actions', 'data-setup-revision', (string) $room->fresh()->setup_revision)->click('[data-ready]')->assertSee('Retirer ma confirmation');
        $chief->assertEnabled('button:has-text("Lancer le quiz")')->click('button:has-text("Lancer le quiz")')->assertSee('Quiz · Question 1 / 5')->assertNoJavaScriptErrors();
        expect($room->fresh()->game_setup['packs'])->toBe([]);
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
