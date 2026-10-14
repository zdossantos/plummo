<?php

use App\Models\Room;
use App\Services\GameEngine;

beforeEach(function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    test()->withVite();
    pest()->browser()->timeout(15000);
});

afterEach(fn () => pest()->browser()->timeout(5000));

it('selects all available challenges instead of packs and plays the cups from a phone', function () {
    $screen = visit('/host', ['viewport' => ['width' => 1280, 'height' => 720]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $phone = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $phone->click('[data-choose-game]')->click('[data-game-option="mini_games"]')->assertSee('Prepare mini games')->assertDontSee('The packs');
        $phone->click('[data-choose-packs]')->assertPresent('[data-mini-trial="cups"]')->assertMissing('[data-mini-trial="author"]');
        expect($phone->page()->evaluate('() => [...document.querySelectorAll("[data-mini-trial]")].every(e => e.checked)'))->toBeTrue();
        foreach (['memory', 'hide', 'count'] as $trial) {
            $phone->click('[data-mini-trial="'.$trial.'"]');
        }
        $phone->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('1 challenges selected')->fill('game-rounds', '5')->assertMissing('[data-ready]')->click('Start mini games')->assertSee('Watch the big screen!');
        $screen->assertPresent('[data-mini-scene="cups"]')->assertMissing('.page-controls');
        $phone->assertMissing('[data-mini-scene]')->assertSee('Which cup is hiding the Plummo?')->assertEnabled('[data-mini-choice="0"]')->click('[data-mini-choice="0"]');
        $phone->assertSee('The correct answer:')->assertNoJavaScriptErrors();
        $screen->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('keeps each mini game readable on TV and phone without scroll or revealing phone scenes', function (string $trial, bool $phone) {
    $screen = visit('/host', ['viewport' => ['width' => 1280, 'height' => 720]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $controller = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        if ($trial === 'author') {
            visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
            visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Morgan')->click('Continue')->click('Enter the room')->assertSee('Morgan');
        }
        prepareReadyGame($room, ['type' => 'mini_games', 'trials' => [$trial], 'rounds' => 5, 'duration' => 20]);
        $game = app(GameEngine::class)->start($room, ['type' => 'mini_games', 'trials' => [$trial], 'rounds' => 5, 'duration' => 20]);
        // Hold this stage while measuring real viewport geometry.
        $state = $game->state;
        $state['deadline'] = app(GameEngine::class)->time() + 150;
        app(GameEngine::class)->save($game, ['state' => $state]);
        $page = ($phone ? $controller : $screen)->refresh()->assertPresent('[data-testid="mini-play"]')->assertMissing('.page-controls');
        $page->page()->evaluate('() => document.fonts.ready');
        if ($phone) {
            $page->assertMissing('[data-mini-scene]');
        } elseif ($trial !== 'author') {
            $page->assertPresent('[data-mini-scene="'.$trial.'"]');
        }
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight'))->toBeTrue();
        expect($page->page()->evaluate('() => { const e = document.querySelector(".mini-question"); return e.scrollHeight <= e.clientHeight + 1 && e.getBoundingClientRect().bottom < innerHeight; }'))->toBeTrue();
        $page->screenshot(filename: 'mini-'.$trial.'-'.($phone ? 'phone' : 'tv'));
        if ($trial !== 'author' && $trial !== 'hide') {
            $state['phase'] = 'answer';
            app(GameEngine::class)->save($game, ['state' => $state]);
            $page->refresh()->assertPresent('[data-testid="mini-play"]');
            if ($phone) {
                $page->assertPresent('[data-mini-choice="0"]');
                expect($page->page()->evaluate('() => [...document.querySelectorAll("[data-mini-choice]")].every(e => { const r=e.getBoundingClientRect(); return r.height >= 40 && r.bottom <= innerHeight; })'))->toBeTrue();
            }
            if (! $phone && $trial === 'memory') {
                $page->assertMissing('[data-mini-scene]');
            }
        }
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
})->with([
    ['memory', false], ['memory', true], ['hide', false], ['hide', true],
    ['count', false], ['count', true], ['cups', false], ['cups', true],
    ['author', false], ['author', true],
]);
