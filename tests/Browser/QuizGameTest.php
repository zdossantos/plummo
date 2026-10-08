<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;

it('plays a quiz with a shared screen and two separate phones then returns to the persistent lobby', function () {
    // Pest serves several browser contexts through one application instance.
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
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')
            ->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')
            ->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $first->click('Session')->fill('session-target', '50')->click('Save target')->assertSee('Target: 50 points');
        $first->click('Play')->click('Continue')->click('input[type="checkbox"][value="'.$pack->id.'"]');
        $first->click('Continue')->assertSee('5 unseen questions')->fill('game-rounds', '5');
        $first->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $first->assertSee('Quiz · Question 1 / 5');
        $second->assertSee('Quiz · Question 1 / 5');
        $screen->assertSee('Quiz · Question 1 / 5')->assertNoJavaScriptErrors();
        $first->page()->locator('button:has-text("AA")')->click(['noWaitAfter' => true]);
        $first->assertSee('Answer saved.');
        $second->page()->locator('button:has-text("BB")')->click(['noWaitAfter' => true]);
        $second->assertSee('0 points for this question');
        $first->assertSee('100 points for this question');
        $screen->assertSee('Mini-game leaderboard')->assertSee('Well done, Camille!')->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->assertSee('Session target reached');
        $first->assertSee('Mini-game leaderboard');
        $first->page()->locator('button:has-text("Back to lobby")')->click(['noWaitAfter' => true]);
        $first->click('Session')->assertSee('The session')->assertDontSee('Prepare a quiz');
        $screen->assertSee('Everyone plays.')->assertSee('Camille')->assertSee('100 Points');
        $identity = $room->players()->where('name', 'Camille')->sole()->id;
        $first->refresh()->assertSee('Camille')->assertSee('100 Points')->assertDontSee('Prepare a quiz');
        expect($room->players()->where('name', 'Camille')->sole()->id)->toBe($identity);
        $first->click('Session')->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->fill('session-extra', '500')->click('Extend session')->assertSee('Target: 600 points')->click('Play')->assertSee('Prepare a quiz');
        $screen->assertSee('Target: 600 points')->assertSee('100 Points');
        $second->refresh()->assertSee('Alex')->assertSee('0 Points');
        expect($room->fresh()->point_target)->toBe(600);
        $first->assertNoJavaScriptErrors();
        $second->assertNoJavaScriptErrors();
        expect($room->players()->count())->toBe(2);
        $screen->screenshot(filename: 'quiz-screen-results-lobby');
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
