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
        $second->assertDontSee($word);
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
        $second->fill('#drawing-guess', mb_substr($word, 1));
        $second->page()->locator('button:has-text("Guess")')->click(['noWaitAfter' => true]);
        $second->assertSee('Almost!');
        $second->fill('#drawing-guess', $word);
        $second->page()->locator('button:has-text("Guess")')->click(['noWaitAfter' => true]);
        $screen->assertSee('The word was: '.$word);
        $second->assertSee('Choose a word');
        $game->refresh();
        $word = $game->state['round']['words'][0];
        $second->page()->locator('[data-testid="drawing-word"]')->first()->click(['noWaitAfter' => true]);
        $first->assertPresent('#drawing-guess')->fill('#drawing-guess', $word);
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
