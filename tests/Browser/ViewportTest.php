<?php

use App\Models\User;

function assertViewportFits($page): void
{
    $outside = $page->page()->evaluate('() => Array.from(document.querySelectorAll("button, input, select, textarea, a, [role=dialog]" )).filter(e => { if (!e.checkVisibility() || e.closest("[inert]")) return false; const r=e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && (r.left < -1 || r.top < -1 || r.right > innerWidth+1 || r.bottom > innerHeight+1); }).map(e => e.id || e.getAttribute("aria-label") || e.textContent.trim().slice(0,80))');
    expect($outside)->toBe([]);
    expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
}

it('keeps the focused login field visible when the keyboard reduces the viewport', function () {
    $page = visit('/admin/login', ['viewport' => ['width' => 390, 'height' => 844]]);
    $page->fill('admin-password', 'keyboard-check');
    $page->page()->setViewportSize(390, 300);
    $visible = $page->page()->evaluate('() => new Promise(resolve => setTimeout(() => resolve(document.querySelector("#admin-password").checkVisibility()), 200))');
    expect($visible)->toBeTrue();
    expect($page->page()->evaluate('() => document.activeElement.id'))->toBe('admin-password');
    assertViewportFits($page);
    $page->assertNoJavaScriptErrors();
});

it('keeps the message scene inside the visual viewport when the mobile keyboard pans it', function () {
    $room = openRoom();
    test()->withVite();
    try {
        $page = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US');
        $page->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $page->click('.message-shortcut')->fill('chat-message', 'Salut');
        $page->page()->evaluate('() => { window.keyboardViewport = { height: 360, top: 180 }; Object.defineProperties(visualViewport, { height: { configurable: true, get: () => keyboardViewport.height }, offsetTop: { configurable: true, get: () => keyboardViewport.top } }); visualViewport.dispatchEvent(new Event("resize")); }');
        $page->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 200))');
        expect($page->page()->evaluate('() => document.querySelector(".viewport-shell").getBoundingClientRect().top'))->toBe(180);
        $page->page()->evaluate('() => { keyboardViewport.top = 220; visualViewport.dispatchEvent(new Event("scroll")); }');
        $page->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 200))');
        expect($page->page()->evaluate('() => document.querySelector(".viewport-shell").getBoundingClientRect().top'))->toBe(220);
        expect($page->page()->evaluate('() => document.activeElement.id'))->toBe('chat-message');
        if (getenv('PLUMMO_UI_CAPTURE')) {
            $page->page()->screenshot(false, 'keyboard-offset');
        }
        expect($page->page()->evaluate('() => Array.from(document.querySelectorAll(".viewport-shell button, .viewport-shell input, label[for=chat-message], #chat-hint")).filter(e => e.checkVisibility()).every(e => { const r = e.getBoundingClientRect(); return r.top >= visualViewport.offsetTop && r.bottom <= visualViewport.offsetTop + visualViewport.height; })'))->toBeTrue();
        $page->page()->evaluate('() => { document.activeElement.blur(); keyboardViewport.height = 844; keyboardViewport.top = 0; visualViewport.dispatchEvent(new Event("resize")); }');
        $page->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 200))');
        expect($page->page()->evaluate('() => document.querySelector(".viewport-shell").getBoundingClientRect().top'))->toBe(0);
        expect($page->page()->evaluate('() => document.querySelector("#chat-message").value'))->toBe('Salut');
        assertViewportFits($page);
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('shows room errors over the message scene without moving its controls', function () {
    $room = openRoom();
    test()->withVite();
    try {
        $page = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US');
        $page->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $page->click('.message-shortcut')->fill('chat-message', 'Salut');
        $before = $page->page()->evaluate('() => document.querySelector("#chat-message").getBoundingClientRect().top');
        $page->page()->evaluate('() => { const originalFetch = window.fetch; window.fetch = (url, options) => String(url).endsWith("/chat") ? Promise.resolve(new Response(JSON.stringify({ errors: { message: ["Message temporarily unavailable"] } }), { status: 422, headers: { "Content-Type": "application/json" } })) : originalFetch(url, options); }');
        $page->click('Send message')->assertSee('Message temporarily unavailable');
        expect($page->page()->evaluate('() => document.querySelector("#chat-message").getBoundingClientRect().top'))->toBe($before);
        $page->assertPresent('.room-notice[role="alert"]');
        if (getenv('PLUMMO_UI_CAPTURE')) {
            $page->page()->screenshot(false, 'message-error-toast');
        }
        assertViewportFits($page);
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('shows game preparation together without steps or scroll', function (int $width, int $height) {
    $room = openRoom();
    test()->withVite();
    try {
        $page = visit('/join/'.$room->code, ['viewport' => compact('width', 'height')])->withLocale('en-US');
        $page->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        foreach (['game-type', 'game-rounds', 'game-duration'] as $id) {
            expect($page->page()->evaluate('() => document.getElementById("'.$id.'").checkVisibility()'))->toBeTrue();
        }
        $page->assertDontSee('Continue')->assertSee('Start quiz');
        assertViewportFits($page);
        $page->click('[data-choose-game]')->assertPresent('[data-slot="drawer-content"][data-state="open"]');
        assertViewportFits($page);
        $page->assertPresent('[data-game-option="quiz"][aria-pressed="true"]')->assertMissing('[data-game-option="phrase"]')->assertMissing('[data-game-option="drawing"]')->click('[data-game-option="quiz"]')->assertSee('Prepare a quiz');
        $page->click('[data-choose-packs]')->assertPresent('[data-slot="drawer-content"][data-state="open"]');
        assertViewportFits($page);
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
})->with([[320, 400], [390, 844], [844, 390], [1440, 900]]);

it('keeps the wardrobe and genuine accessory drawers usable without scroll on phone and desktop', function (int $width, int $height, string $locale) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $room = openRoom();
    test()->withVite();
    try {
        $english = $locale === 'en-US';
        $page = visit('/join/'.$room->code, ['viewport' => compact('width', 'height')])->withLocale($locale);
        $page->fill('player-name', 'Camille')->click($english ? 'Continue' : 'Continuer');
        assertViewportFits($page);
        foreach ($english ? ['Head', 'Face', 'Neck', 'Hands'] : ['Tête', 'Visage', 'Cou', 'Mains'] as $slot) {
            $page->click($slot)->assertPresent('[data-slot="drawer-content"][data-state="open"]');
            assertViewportFits($page);
            $page->page()->locator('.drawer-accessories button')->first()->click();
            $page->click('button[aria-label="'.($english ? 'Close' : 'Fermer').'"]');
        }
        $page->click($english ? 'Enter the room' : 'Entrer dans le salon')->assertSee('Camille');
        expect($room->players()->sole()->accessories)->toHaveCount(4);
        assertViewportFits($page);
        $page->screenshot(filename: 'viewport-'.$width.'-'.$height.'-'.$locale);
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
})->with([[320, 568, 'en-US'], [390, 844, 'fr-FR'], [1440, 900, 'en-US']]);

it('keeps administration forms and navigation inside a small phone viewport', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    try {
        $page = visit('/admin/login', ['viewport' => ['width' => 320, 'height' => 568]])->withLocale('en-US');
        assertViewportFits($page);
        $page->fill('admin-email', $admin->email)->fill('admin-password', 'password')->click('Sign in')->assertSee('Welcome to Plummo administration.');
        assertViewportFits($page);
        $page->click('.admin-nav a[href="/admin/contents"]')->click('Add content');
        assertViewportFits($page);
        $page->click('.page-deck:visible > .page-controls > button[aria-label="Next"]');
        $long = str_repeat('😀 long question ', 25);
        $page->fill('textarea:visible', $long);
        assertViewportFits($page);
        $page->assertNoJavaScriptErrors();
    } finally {
        $admin->delete();
    }
});
