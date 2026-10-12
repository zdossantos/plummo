<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;
use App\Services\DrawingGame;
use App\Services\GameEngine;

it('keeps a full bonus pocket above a usable drawing canvas at every controller size', function (int $width, int $height) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    test()->withVite();
    visit('/host')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    $pack = Pack::findOrFail(drawingPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    try {
        // Join at a comfortable size; then test the actual game at each usable viewport.
        $phone = visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        visit('/join/'.$room->code)->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $engine = app(GameEngine::class);
        $game = $engine->start($room, ['type' => 'drawing', 'packs' => [$pack->id], 'rounds' => 1, 'duration' => 150, 'bonuses' => true]);
        $player = $room->players()->where('name', 'Camille')->sole();
        app(DrawingGame::class)->choose($room, $player, 0, $game->id, 1);
        $game->refresh();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'one', 'kind' => 'shield'], ['id' => 'two', 'kind' => 'bolt']];
        $state['bonuses']['pending'][$player->id] = ['id' => 'three', 'kind' => 'soap'];
        $game->update(['state' => $state]);
        $phone->page()->setViewportSize($width, $height);
        $phone->refresh()->assertVisible('[data-testid="drawing-board"]')->assertVisible('.bonus-pending');
        $phone->page()->evaluate('() => document.fonts.ready');
        $geometry = $phone->page()->evaluate(<<<'JS'
        () => {
            const canvas = document.querySelector('[data-testid="drawing-board"]').getBoundingClientRect();
            const pocket = document.querySelector('.bonus-pocket').getBoundingClientRect();
            const objects = [...document.querySelectorAll('.bonus-object, .bonus-pending, [data-drawing-settings], .game-pause')].map(e => e.getBoundingClientRect());
            return {
                canvasHeight: canvas.height,
                canvasWidth: canvas.width,
                above: pocket.bottom <= canvas.top,
                reachable: objects.every(r => r.top >= 0 && r.bottom <= innerHeight && r.left >= 0 && r.right <= innerWidth),
                overflow: document.documentElement.scrollHeight > innerHeight || document.documentElement.scrollWidth > innerWidth
            };
        }
        JS);
        expect($geometry['canvasHeight'])->toBeGreaterThanOrEqual($height < 420 ? 100 : 170);
        expect($geometry['canvasWidth'])->toBeGreaterThanOrEqual(250);
        expect($geometry['above'])->toBeTrue();
        expect($phone->page()->evaluate('() => { const canvas=document.querySelector("[data-testid=drawing-board]").getBoundingClientRect(); return [...document.querySelectorAll(".drawing-toolbar > button")].every(e => { const r=e.getBoundingClientRect(); const top=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2); return r.width >= 40 && r.height >= 40 && !!top?.closest("button") && (r.bottom <= canvas.top || r.top >= canvas.bottom || r.right <= canvas.left || r.left >= canvas.right); }); }'))->toBeTrue();
        expect($geometry['reachable'])->toBeTrue();
        expect($geometry['overflow'])->toBeFalse();
        $phone->click('[data-drawing-settings]')->assertVisible('.drawing-settings-drawer');
        $phone->click('[data-drawing-color="#ffffff"]')->assertAttribute('[data-drawing-color="#ffffff"]', 'aria-pressed', 'true');
        $phone->assertNoJavaScriptErrors()->screenshot(filename: 'controller-drawing-'.$width.'-'.$height);
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
})->with([[320, 568], [360, 640], [375, 667], [390, 664], [430, 740], [667, 375], [844, 390], [768, 1024]]);
