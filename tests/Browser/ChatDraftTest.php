<?php

use App\Models\Pack;

it('keeps the message drawer and draft through new game actions until a successful send', function () {
    $room = openRoom();
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    test()->withVite();
    try {
        $phone = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')
            ->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $phone->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->fill('game-rounds', '5');
        $phone->click('.message-shortcut')->fill('chat-message', 'Un brouillon');
        $phone->assertVisible('.chat-drawer[role="dialog"]');
        $phone->click('.chat-drawer button[aria-label="Close"]');
        $phone->click('.message-shortcut')->assertValue('chat-message', 'Un brouillon');

        // The drawer must leave the real game controls usable.
        confirmReadyPhone($phone, $room)->assertEnabled('button:has-text("Start quiz")');
        $phone->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Quiz · Question 1 / 5');
        $phone->assertVisible('.chat-drawer')->assertValue('chat-message', 'Un brouillon');
        $phone->assertDisabled('.chat-drawer button[type="submit"]');
        $phone->fill('chat-message', 'Un brouillon poursuivi');
        $phone->page()->locator('.game-choice[data-answer="0"] > button:first-child')->click(['noWaitAfter' => true]);
        $phone->assertSee('Quiz · Question 2 / 5');
        $phone->assertVisible('.chat-drawer')->assertValue('chat-message', 'Un brouillon poursuivi');
        $phone->assertDisabled('.chat-drawer button[type="submit"]');

        $phone->page()->locator('button[aria-label="Pause game"]')->click(['noWaitAfter' => true]);
        $phone->assertSee('Game paused');
        $phone->page()->locator('button:has-text("Send message")')->click(['noWaitAfter' => true]);
        $phone->assertValue('chat-message', '');
        expect($room->players()->sole()->chat_message)->toBe('Un brouillon poursuivi');
        $phone->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        $tag->contents()->delete();
        $pack->delete();
        $tag->delete();
    }
});

it('keeps a refused or failed message after closing and reopening its drawer', function () {
    $room = openRoom();
    test()->withVite();
    try {
        $phone = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')
            ->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $phone->click('.message-shortcut')->fill('chat-message', 'Toujours présent');
        $phone->page()->evaluate('() => { const original = window.fetch; window.chatFailure = "refused"; window.fetch = (url, options) => String(url).endsWith("/chat") ? (window.chatFailure === "refused" ? Promise.resolve(new Response(JSON.stringify({ errors: { message: ["Message temporarily unavailable"] } }), { status: 422, headers: { "Content-Type": "application/json" } })) : Promise.reject(new TypeError("Failed to fetch"))) : original(url, options); }');
        $phone->click('Send message')->assertSee('Message temporarily unavailable')->assertValue('chat-message', 'Toujours présent');
        $phone->click('.chat-drawer button[aria-label="Close"]')->click('.message-shortcut')->assertValue('chat-message', 'Toujours présent');
        $phone->page()->evaluate('() => { window.chatFailure = "network"; }');
        $phone->click('Send message')->assertValue('chat-message', 'Toujours présent');
        $phone->click('.chat-drawer button[aria-label="Close"]')->click('.message-shortcut')->assertValue('chat-message', 'Toujours présent');
        expect($room->players()->sole()->chat_message)->toBeNull();
        $phone->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('clears only the sent draft after closing during a pending send', function (bool $editDraft) {
    $room = openRoom();
    test()->withVite();
    try {
        $phone = visit('/join/'.$room->code)->withLocale('en-US')
            ->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $phone->click('.message-shortcut')->fill('chat-message', 'Premier message');
        $phone->page()->evaluate('() => { const original = window.fetch; window.fetch = async (url, options) => { const response = await original(url, options); if (String(url).endsWith("/chat")) return new Promise(resolve => { window.finishChatSend = () => resolve(response); }); return response; }; }');
        $phone->page()->locator('button:has-text("Send message")')->click(['noWaitAfter' => true]);
        $phone->assertDisabled('#chat-message');
        $phone->click('.chat-drawer button[aria-label="Close"]')->click('.message-shortcut');
        if ($editDraft) {
            $phone->fill('chat-message', 'Nouveau brouillon');
        }
        $phone->page()->waitForFunction('() => typeof window.finishChatSend === "function"');
        $phone->page()->evaluate('() => window.finishChatSend()');
        $phone->assertSee('Wait')->assertValue('chat-message', $editDraft ? 'Nouveau brouillon' : '');
        expect($room->players()->sole()->chat_message)->toBe('Premier message');
        $phone->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
})->with([false, true]);
