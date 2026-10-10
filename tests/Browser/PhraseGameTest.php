<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;

it('writes privately on three phones, presents anonymously, votes and returns to the lobby', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(phrasePack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $third = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Sam')->click('Continue')->click('Enter the room')->assertSee('Sam');
        $first->click('[data-choose-game]')->click('[data-game-option="phrase"]')->assertSee('Prepare sentences')->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('5 unseen prompts');
        $first->page()->locator('button:has-text("Start sentences")')->click(['noWaitAfter' => true]);
        $first->assertSee('Your secret ending');
        $second->assertSee('Your secret ending');
        $third->assertSee('Your secret ending');
        $first->fill('#phrase-suffix', 'Camille secret')->assertSee('Draft saved');
        $screen->assertDontSee('Camille secret');
        $second->assertDontSee('Camille secret');
        // Several complete server snapshots must leave the current text intact.
        $first->assertValue('#phrase-suffix', 'Camille secret');
        $first->page()->locator('button:has-text("Submit my sentence")')->click(['noWaitAfter' => true]);
        $first->assertSee('Sentence submitted.');
        $second->fill('#phrase-suffix', 'Alex secret');
        $second->page()->locator('button:has-text("Submit my sentence")')->click(['noWaitAfter' => true]);
        $second->assertSee('Sentence submitted.');
        $third->fill('#phrase-suffix', 'Sam secret');
        $third->page()->locator('button:has-text("Submit my sentence")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Discover the sentences, without their authors')->assertNoJavaScriptErrors();
        // The first phone is already mounted while the server progresses through presentation.
        $first->page()->getByText('Vote for your favourite sentence')->waitFor(['state' => 'visible', 'timeout' => 25000]);
        $first->assertSee('Vote for your favourite sentence');
        $second->assertSee('Vote for your favourite sentence');
        $third->assertSee('Vote for your favourite sentence');
        $screen->assertSee('Vote for your favourite sentence');
        foreach ([$first, $second, $third, $screen] as $page) {
            expect($page->page()->evaluate('() => { const entries = Array.from(document.querySelectorAll("[data-testid^=phrase-entry-]")); return entries.length === 3 && entries.every(e => { const s = getComputedStyle(e); return s.backgroundColor !== "rgba(0, 0, 0, 0)" && s.opacity === "1"; }); }'))->toBeTrue();
        }
        $entries = collect(Game::where('room_id', $room->id)->sole()->state['round']['entries'])->keyBy('author');
        $players = $room->players()->orderBy('id')->get();
        $first->assertDisabled('[data-testid="phrase-entry-'.$entries[$players[0]->id]['id'].'"]');
        $first->page()->locator('[data-testid="phrase-entry-'.$entries[$players[1]->id]['id'].'"]')->click(['noWaitAfter' => true]);
        $first->assertSee('Vote saved!');
        expect($first->page()->evaluate('() => Array.from(document.querySelectorAll("[data-testid^=phrase-entry-]")).every(e => getComputedStyle(e).opacity === "1" && getComputedStyle(e).backgroundColor !== "rgba(0, 0, 0, 0)")'))->toBeTrue();
        $second->page()->locator('[data-testid="phrase-entry-'.$entries[$players[0]->id]['id'].'"]')->click(['noWaitAfter' => true]);
        $second->assertSee('Vote saved!');
        $third->page()->locator('[data-testid="phrase-entry-'.$entries[$players[0]->id]['id'].'"]')->click(['noWaitAfter' => true]);
        $third->assertSee('The authors and their points')->assertSee('2 votes · 130 points');
        $screen->assertSee('Mini-game leaderboard')->assertSee('Well done, Camille!');
        $first->assertSee('Mini-game leaderboard');
        $first->page()->locator('button:has-text("Back to lobby")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Everyone plays.')->assertSee('130 Points');
        $first->assertNoJavaScriptErrors();
        $second->assertNoJavaScriptErrors();
        $third->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
