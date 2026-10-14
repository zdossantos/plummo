<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;
use Pest\Browser\Execution;

it('shows an accessible rules button on the ordinary player phone', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $room = openRoom();
    test()->withVite();
    try {
        visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertMissing('[data-ready]');
        $guest = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('fr-FR')->fill('player-name', 'Alex')->click('Continuer')->click('Entrer dans le salon')->assertSee('Je suis prêt')->assertPresent('[data-game-rules]');
        expect($guest->page()->evaluate('() => document.querySelector("[data-game-rules]").getBoundingClientRect().height'))->toBeGreaterThanOrEqual(44);
        $guest->assertMissing('#game-type')->assertMissing('#game-duration')->screenshot(filename: 'player-guest-preparation-fr');
        $guest->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('clears a preparation error after the chief corrects the settings', function () {
    $room = openRoom();
    test()->withVite();
    try {
        $chief = visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $chief->page()->evaluate('() => { const originalFetch = window.fetch; window.fetch = (url, options) => String(url).endsWith("/game-setup") && JSON.parse(options.body).duration === 40 ? Promise.resolve(new Response(JSON.stringify({ errors: { duration: ["Invalid game duration"] } }), { status: 422, headers: { "Content-Type": "application/json" } })) : originalFetch(url, options); }');
        $chief->fill('game-duration', '40')->assertSee('Invalid game duration');
        $chief->fill('game-duration', '50')->assertMissing('[data-ready]')->assertDontSee('Invalid game duration');
        expect($room->fresh()->game_setup['duration'])->toBe(50);
        $chief->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('lets both phones read rules and only the guest confirm before the chief starts', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $chief = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $guest = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $chief->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->fill('game-rounds', '5');
        Execution::instance()->waitForExpectation(fn () => expect($room->fresh()->game_setup['rounds'])->toBe(5));
        $guest->click('[data-game-rules]')->assertSee('Quiz')->assertSee('Goal')->click('[data-slot="drawer-content"] button:has-text("Close")');
        $chief->assertDisabled('button:has-text("Start quiz")')->assertMissing('[data-ready]');
        $guest->screenshot(filename: 'player-guest-preparation');
        confirmReadyPhone($guest, $room);
        confirmReadyPhone($chief, $room)->assertEnabled('button:has-text("Start quiz")');
        $guest->click('[data-ready]')->assertSee('I’m ready');
        $chief->assertDisabled('button:has-text("Start quiz")');
        $guest->click('[data-ready]');
        $chief->fill('game-duration', '40')->assertMissing('[data-ready]');
        $guest->assertSee('I’m ready');
        confirmReadyPhone($chief, $room);
        confirmReadyPhone($guest, $room);
        $chief->assertEnabled('button:has-text("Start quiz")')->click('button:has-text("Start quiz")')->assertSee('Quiz · Question 1 / 5');
        $guest->click('[data-game-rules]')->assertSee('Goal')->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('Quiz · Question 1 / 5');
        $screen->assertDontSee('Game paused');
        expect($room->players()->whereNotNull('ready_revision')->count())->toBe(0);
        $chief->assertNoJavaScriptErrors();
        $guest->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
