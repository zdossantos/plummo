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
