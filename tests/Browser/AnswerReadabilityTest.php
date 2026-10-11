<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;
use App\Services\GameEngine;

it('uses the available answer height for long text and keeps the TV question prominent', function (int $width, int $height, bool $phone) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    test()->withVite();
    $screen = visit('/', ['viewport' => compact('width', 'height')])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    $contents = $tag->contents()->get();
    $question = 'Quel personnage accompagne les voyageurs dans cette aventure et les aide à retrouver leur chemin à travers la forêt ?';
    $answer = 'Un petit personnage qui accompagne les voyageurs et les aide à retrouver leur chemin à travers la forêt enchantée.';
    foreach ($contents as $content) {
        $content->update(['payload' => ['question' => $question, 'choices' => array_fill(0, 4, $answer), 'correct' => 0]]);
    }
    test()->withVite();
    try {
        $controller = visit('/join/'.$room->code, ['viewport' => compact('width', 'height')])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        app(GameEngine::class)->start($room, ['type' => 'quiz', 'packs' => [$pack->id], 'rounds' => 5, 'duration' => 150]);
        $page = ($phone ? $controller : $screen)->refresh();
        $page->assertSee($question)->assertSee($answer)->assertMissing('.page-controls');
        $page->page()->evaluate('() => document.fonts.ready');
        expect($page->page()->evaluate('() => Array.from(document.querySelectorAll(".choice-face > .text-summary")).every(e => e.scrollHeight <= e.clientHeight + 1)'))->toBeTrue();
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight'))->toBeTrue();
        expect($page->page()->evaluate('() => { const e = document.querySelector(".game-question h1"); return e.scrollHeight <= e.clientHeight + 1; }'))->toBeTrue();
        if ($width >= 1280) {
            expect($page->page()->evaluate('() => parseFloat(getComputedStyle(document.querySelector(".game-question h1")).fontSize) > parseFloat(getComputedStyle(document.querySelector(".choice-face")).fontSize)'))->toBeTrue();
        }
        $page->assertNoJavaScriptErrors()->screenshot(filename: 'answer-readability-'.$width.'-'.$height);
    } finally {
        $room->delete();
        Content::whereIn('id', $contents->modelKeys())->delete();
        $pack->delete();
        $tag->delete();
    }
})->with([[1920, 1080, false], [1280, 720, false], [390, 844, true]]);

it('shows a long phrase during presentation and voting using the available space', function (int $width, int $height, bool $phone) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    test()->withVite();
    $screen = visit('/', ['viewport' => compact('width', 'height')])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    $pack = Pack::findOrFail(phrasePack());
    $tag = $pack->tags()->sole();
    $contents = $tag->contents()->get();
    try {
        $controller = visit('/join/'.$room->code, ['viewport' => compact('width', 'height')])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Morgan')->click('Continue')->click('Enter the room')->assertSee('Morgan');
        $game = app(GameEngine::class)->start($room, ['type' => 'phrase', 'packs' => [$pack->id], 'rounds' => 1, 'duration' => 150]);
        $text = 'Pour retrouver leur chemin dans la forêt enchantée, les voyageurs demandent au petit personnage de dessiner une carte avec tous les arbres et les chemins qui les séparent encore de leur destination.';
        $state = $game->state;
        $state['phase'] = 'presenting';
        $state['deadline'] = app(GameEngine::class)->time() + 150;
        $state['round']['entries'] = $room->players()->get()->map(fn ($player) => ['id' => (string) $player->id, 'author' => $player->id, 'text' => $text])->all();
        app(GameEngine::class)->save($game, ['state' => $state]);
        $page = ($phone ? $controller : $screen)->refresh();
        $page->assertSee($text);
        $page->page()->evaluate('() => document.fonts.ready');
        expect($page->page()->evaluate('() => { const e = document.querySelector("blockquote .text-summary"); return e.scrollHeight <= e.clientHeight + 1; }'))->toBeTrue();
        $state['phase'] = 'voting';
        app(GameEngine::class)->save($game, ['state' => $state]);
        $page->refresh()->assertSee('Vote for your favourite sentence');
        if ($phone) {
            $page->assertPresent('.text-reader-trigger');
            expect($page->page()->evaluate('() => Array.from(document.querySelectorAll(".choice-face > .text-summary")).every(e => getComputedStyle(e).webkitLineClamp === "none")'))->toBeTrue();
        } else {
            expect($page->page()->evaluate('() => Array.from(document.querySelectorAll(".choice-face > .text-summary")).every(e => e.scrollHeight <= e.clientHeight + 1)'))->toBeTrue();
            $page->assertMissing('.text-reader-trigger');
        }
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight'))->toBeTrue();
        $page->assertNoJavaScriptErrors()->screenshot(filename: 'phrase-readability-'.$width.'-'.$height);
    } finally {
        $room->delete();
        Content::whereIn('id', $contents->modelKeys())->delete();
        $pack->delete();
        $tag->delete();
    }
})->with([[1920, 1080, false], [1280, 720, false], [390, 844, true]]);
