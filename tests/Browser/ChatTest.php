<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;

it('shows escaped chat bubbles, returns to game controls and celebrates tied winners', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/', ['reducedMotion' => 'reduce', 'viewport' => ['width' => 1920, 'height' => 1080]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $first->click('.message-shortcut')->fill('#chat-message', '<b>Salut</b>');
        $first->page()->locator('button:has-text("Send message")')->click(['noWaitAfter' => true]);
        $screen->assertSee('<b>Salut</b>')->assertNoJavaScriptErrors();
        expect($screen->page()->locator('[data-testid="chat-bubble"] b')->count())->toBe(0);
        expect($screen->page()->evaluate('() => getComputedStyle(document.querySelector("[data-testid=chat-bubble]")).animationName'))->toBe('none');
        $first->assertSee('Wait');
        $first->click('button[aria-label="Play"]')->click('[data-choose-packs]');
        $first->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->fill('game-rounds', '5');
        $first->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $first->assertSee('Question')->assertMissing('#chat-message');
        $second->assertSee('Question')->assertMissing('#chat-message');
        // Select a wrong definitive answer: chatting is allowed while Alex still plays.
        $first->page()->locator('.game-choice > button:first-child')->nth(1)->click(['noWaitAfter' => true]);
        $first->assertSee('Answer saved')->click('.message-shortcut')->assertPresent('#chat-message');
        $screen->page()->locator('[data-testid="chat-bubble"]')->waitFor(['state' => 'hidden', 'timeout' => 9000]);
        $first->fill('#chat-message', 'À toi Alex');
        $first->page()->locator('button:has-text("Send message")')->click(['noWaitAfter' => true]);
        $screen->assertSee('À toi Alex');
        // Finish the game through its existing server deadline, with a shared first rank.
        $game = Game::where('room_id', $room->id)->sole();
        $state = $game->state;
        $state['phase'] = 'reveal';
        $state['previous_phase'] = 'reveal';
        $state['number'] = 5;
        $state['deadline'] = microtime(true) + 2;
        $state['scores'] = $room->players()->pluck('id')->mapWithKeys(fn ($id) => [$id => 65])->all();
        $state['round']['awards'] = $state['scores'];
        $room->players()->update(['score' => 65]);
        $game->update(['state' => $state]);
        $screen->assertSee('Mini-game leaderboard')->assertPresent('.podium-slot-1')->assertPresent('.podium-slot-2');
        expect($screen->page()->locator('[data-testid="winner-avatar"]')->count())->toBe(2);
        expect($screen->page()->evaluate('() => getComputedStyle(document.querySelector("[data-testid=winner-avatar]")).animationName'))->toBe('none');
        $screen->assertNoJavaScriptErrors();
        $first->click('.message-shortcut')->assertPresent('#chat-message');
    } finally {
        $room->delete();
        $pack->delete();
        Content::whereIn('id', $ids)->delete();
        $tag->delete();
    }
});

it('keeps eight full-length bubbles and their avatars separate on the big screen', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $screen = visit('/', ['viewport' => ['width' => 1366, 'height' => 768]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        foreach (range(1, 8) as $index) {
            $room->players()->create(['identity_hash' => hash('sha256', 'chat-layout-'.$index), 'name' => 'Player '.$index, 'color' => 'violet', 'accessories' => [], 'score' => 0, 'last_seen_at' => now(), 'connected_since' => now(), 'chat_message' => str_repeat('😀', 80), 'chat_sent_at' => now()]);
        }
        $screen->assertSee('Player 8');
        $boxes = $screen->page()->evaluate('() => Array.from(document.querySelectorAll("[data-testid=chat-bubble]")).map(e => { const r = e.getBoundingClientRect(); return {left:r.left,right:r.right,top:r.top,bottom:r.bottom}; })');
        expect($boxes)->toHaveCount(8);
        foreach ($boxes as $i => $a) {
            foreach (array_slice($boxes, $i + 1) as $b) {
                expect($a['right'] <= $b['left'] || $b['right'] <= $a['left'] || $a['bottom'] <= $b['top'] || $b['bottom'] <= $a['top'])->toBeTrue();
            }
        }
        $overlaps = $screen->page()->evaluate('() => { const bubbles = Array.from(document.querySelectorAll("[data-testid=chat-bubble]")).map(e => e.getBoundingClientRect()); const avatars = Array.from(document.querySelectorAll("[data-testid=player-dock] svg")).map(e => e.getBoundingClientRect()); return bubbles.some(a => avatars.some(b => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom)); }');
        expect($overlaps)->toBeFalse();
        expect($screen->page()->evaluate('() => { const r = document.querySelector("[data-testid=player-dock]").getBoundingClientRect(); return r.right <= innerWidth && r.bottom <= innerHeight; }'))->toBeTrue();
        $path = $screen->page()->screenshot(true, 'chat-eight-players');
        copy(base_path('tests/Browser/Screenshots/'.$path.'.png'), '/tmp/plummo-chat-eight.png');
        // A long results panel must not push the permanent player dock off the TV.
        $screen->page()->evaluate('() => { document.querySelector("[data-testid=screen-content]").style.minHeight = "1200px"; }');
        expect($screen->page()->evaluate('() => document.querySelector("[data-testid=player-dock]").getBoundingClientRect().bottom <= innerHeight'))->toBeTrue();
        $screen->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});
