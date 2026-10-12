<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;

it('draws from two separate phones and plays every artist before returning to the lobby', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(drawingPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $first->click('[data-choose-game]')->click('[data-game-option="drawing"]')->assertSee('Prepare a drawing game');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('2 drawings');
        $first->page()->locator('button:has-text("Start drawing")')->click(['noWaitAfter' => true]);
        $first->assertSee('Choose a word');
        $second->assertSee('Camille is choosing a word');
        $game = Game::where('room_id', $room->id)->sole();
        $word = $game->state['round']['words'][0];
        $first->page()->locator('[data-testid="drawing-word"]')->first()->click(['noWaitAfter' => true]);
        $first->assertSee('Your word: '.$word);
        $second->assertDontSee($word)->assertMissing('[data-testid="drawing-board"]');
        expect($first->page()->evaluate('() => getComputedStyle(document.documentElement).touchAction'))->toBe('pan-x pan-y');
        expect($first->page()->evaluate('() => getComputedStyle(document.querySelector(".drawing-board")).userSelect'))->toBe('none');
        expect($first->page()->evaluate('() => getComputedStyle(document.querySelector(".drawing-tools")).userSelect'))->toBe('none');
        $second->assertPresent('#drawing-guess');
        expect($second->page()->evaluate('() => getComputedStyle(document.querySelector("#drawing-guess")).userSelect'))->not->toBe('none');
        expect($first->page()->evaluate(<<<'JS'
        () => {
            const zoom = new WheelEvent('wheel', {ctrlKey:true, cancelable:true});
            document.dispatchEvent(zoom);
            const shortcut = new KeyboardEvent('keydown', {key:'+', ctrlKey:true, cancelable:true});
            document.dispatchEvent(shortcut);
            const normal = new KeyboardEvent('keydown', {key:'a', cancelable:true});
            document.dispatchEvent(normal);
            return zoom.defaultPrevented && shortcut.defaultPrevented && !normal.defaultPrevented;
        }
        JS))->toBeTrue();
        $screen->assertDontSee($word);
        $first->page()->evaluate(<<<'JS'
        () => {
        const board = document.querySelector('[data-testid="drawing-board"]');
        const rect = board.getBoundingClientRect();
        for (const [type,x,y] of [['pointerdown',.1,.1]]) {
            board.dispatchEvent(new PointerEvent(type,{pointerId:1,bubbles:true,clientX:rect.left+x*rect.width,clientY:rect.top+y*rect.height}));
        }
        }
        JS);
        $screen->assertPresent('[data-testid="drawing-board"] polyline');
        $first->page()->evaluate(<<<'JS'
        () => {
            const board = document.querySelector('[data-testid="drawing-board"]');
            const rect = board.getBoundingClientRect();
            board.dispatchEvent(new PointerEvent('pointermove', {pointerId:1,bubbles:true,clientX:rect.left+.5*rect.width,clientY:rect.top+.5*rect.height}));
        }
        JS);
        $screen->assertPresent('[data-testid="drawing-board"] polyline[points*=" "]');
        $first->page()->evaluate(<<<'JS'
        () => {
            const board = document.querySelector('[data-testid="drawing-board"]');
            const rect = board.getBoundingClientRect();
            board.dispatchEvent(new PointerEvent('pointerup', {pointerId:1,bubbles:true,clientX:rect.left+.8*rect.width,clientY:rect.top+.8*rect.height}));
        }
        JS);
        $first->click('[data-drawing-color="#ffffff"]')->assertAttribute('[data-drawing-color="#ffffff"]', 'aria-pressed', 'true');
        $first->assertPresent('[data-drawing-color="#ffffff"] svg');
        $first->click('[data-drawing-color="#35236b"]');
        $draw = function ($page, array $points): void {
            $page->page()->evaluate(<<<'JS'
            (points) => {
                const board = document.querySelector('[data-testid="drawing-board"]');
                const rect = board.getBoundingClientRect();
                const dispatch = (type, [x,y]) => board.dispatchEvent(new PointerEvent(type, {pointerId:2,bubbles:true,clientX:rect.left+x*rect.width,clientY:rect.top+y*rect.height}));
                dispatch('pointerdown', points[0]);
                for (const point of points.slice(1)) dispatch('pointermove', point);
                dispatch('pointerup', points.at(-1));
            }
            JS, $points);
        };
        $draw($first, [[.2, .2], [.8, .2], [.8, .8], [.2, .8], [.2, .2]]);
        $screen->assertPresent('[data-tool="brush"]:nth-child(2) polyline');
        $first->click('[data-drawing-color="#f05a78"]')->click('[data-drawing-tool="fill"]')->assertAttribute('[data-drawing-tool="fill"]', 'aria-pressed', 'true');
        $draw($first, [[.4, .5]]);
        $screen->assertPresent('[data-tool="fill"] path[fill="#f05a78"]');
        expect($screen->page()->evaluate('() => { const box = document.querySelector("[data-tool=fill] path").getBBox(); return box.x >= 200 && box.y >= 120 && box.x + box.width <= 800 && box.y + box.height <= 480; }'))->toBeTrue();
        $first->click('[data-drawing-tool="erase"]')->assertAttribute('[data-drawing-tool="erase"]', 'aria-pressed', 'true');
        $draw($first, [[.3, .5], [.7, .5]]);
        $screen->assertPresent('[data-tool="erase"] polyline[stroke="#ffffff"][stroke-width="12"]');
        $first->refresh()->assertPresent('[data-tool="fill"] path')->assertPresent('[data-tool="erase"] polyline');
        $first->click('button:has-text("Undo")');
        $screen->assertMissing('[data-tool="erase"]')->assertPresent('[data-tool="fill"] path');
        $first->page()->setViewportSize(320, 568);
        $first->page()->evaluate('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        expect($first->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        expect($first->page()->evaluate('() => { const rect = document.querySelector("[data-testid=drawing-board]").getBoundingClientRect(); return rect.height >= 120 && rect.top >= 0 && rect.bottom <= innerHeight; }'))->toBeTrue();
        $first->screenshot(filename: 'drawing-tools-phone');
        $screen->screenshot(filename: 'drawing-tools-screen');
        $first->click('button:has-text("Clear")');
        $screen->assertMissing('[data-testid="drawing-board"] g');
        $second->fill('#drawing-guess', mb_substr($word, 1));
        $second->page()->locator('button:has-text("Guess")')->click(['noWaitAfter' => true]);
        $second->assertSee('Almost!');
        $screen->assertSee('Your guesses')->assertSee(mb_substr($word, 1));
        $second->fill('#drawing-guess', $word);
        $second->page()->locator('button:has-text("Guess")')->click(['noWaitAfter' => true]);
        $screen->assertSee('The word was: '.$word);
        $second->assertSee('Choose a word');
        $game->refresh();
        $word = $game->state['round']['words'][0];
        $second->page()->locator('[data-testid="drawing-word"]')->first()->click(['noWaitAfter' => true]);
        $second->assertPresent('[data-testid="drawing-board"]');
        $first->assertMissing('[data-testid="drawing-board"]')->assertPresent('#drawing-guess')->fill('#drawing-guess', $word);
        $first->page()->locator('button:has-text("Guess")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Mini-game leaderboard');
        $first->assertSee('Mini-game leaderboard');
        $first->page()->locator('button:has-text("Back to lobby")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Everyone plays.')->assertSee('Camille')->assertSee('130 Points');
        $screen->assertNoJavaScriptErrors();
        $first->assertNoJavaScriptErrors();
        $second->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});

it('darkens only the TV canvas and opponents phones when the artist launches lightning', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(drawingPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $first->click('[data-choose-game]')->click('[data-game-option="drawing"]')->assertSee('Prepare a drawing game');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('2 drawings');
        $first->click('label:has-text("Prank bonuses")');
        $first->page()->locator('button:has-text("Start drawing")')->click(['noWaitAfter' => true]);
        $first->assertSee('Choose a word');
        $second->assertSee('Camille is choosing a word');
        $game = Game::where('room_id', $room->id)->sole();
        $word = $game->state['round']['words'][0];
        $first->page()->locator('[data-testid="drawing-word"]')->first()->click(['noWaitAfter' => true]);
        $first->assertSee('Your word: '.$word);
        $second->assertDontSee($word)->assertMissing('[data-testid="drawing-board"]');
        $state = $game->fresh()->state;
        $player = $room->players()->where('name', 'Camille')->sole();
        $state['bonuses']['inventory'][$player->id] = [['id' => 'drawing-bolt', 'kind' => 'bolt']];
        $game->update(['state' => $state]);
        $first->refresh()->assertPresent('.bonus-object');
        $first->click('.bonus-object');
        $screen->assertVisible('.bonus-blackout-canvas');
        $second->assertVisible('.bonus-blackout');
        $first->assertMissing('.bonus-blackout');
        expect($screen->page()->evaluate('() => { const layer = document.querySelector(".bonus-blackout-canvas"); const board = document.querySelector(".bonus-canvas"); const a = layer.getBoundingClientRect(), b = board.getBoundingClientRect(); return getComputedStyle(layer).position === "absolute" && getComputedStyle(layer).pointerEvents === "none" && Math.abs(a.width-b.width) < 2 && Math.abs(a.height-b.height) < 2; }'))->toBeTrue();
        $second->fill('drawing-guess', 'Still usable');
        $screen->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 3200))');
        $screen->assertMissing('.bonus-blackout');
        $second->assertMissing('.bonus-blackout');

    } finally {
        Game::where('room_id', $room->id)->delete();
        $room->players()->delete();
        $room->delete();
        $pack->tags()->detach();
        $pack->delete();
        Content::whereIn('id', $ids)->delete();
        $tag->delete();
    }
});
