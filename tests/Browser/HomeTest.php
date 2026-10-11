<?php

use App\Models\Room;

it('lets a phone host and keeps its manual choice after rotating', function () {
    $page = visit('/', ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US');
    $page->assertVisible('[data-testid="host-room"]')->screenshot(filename: 'home-phone-join');
    expect(Room::count())->toBe(0);
    $page->click('[data-testid="host-room"]')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $page->assertVisible('[data-testid="join-room"]')->screenshot(filename: 'home-phone-host-portrait');
        $url = $page->page()->url();
        $page->page()->setViewportSize(844, 390);
        $page->assertVisible('[data-testid="join-room"]');
        expect($page->page()->url())->toBe($url);
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->screenshot(filename: 'home-phone-host-landscape');
        $page->click('[data-testid="join-room"]')->assertVisible('#room-code');
        $page->page()->setViewportSize(1920, 1080);
        $page->assertVisible('#room-code');
        expect($page->page()->url())->toEndWith('/join');
        $page->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('opens the screen on desktop and offers joining without scrolling', function () {
    $page = visit('/', ['viewport' => ['width' => 1920, 'height' => 1080]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $page->assertVisible('[data-testid="join-room"]')->screenshot(filename: 'home-desktop');
        $page->click('[data-testid="join-room"]')->assertVisible('#room-code');
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->screenshot(filename: 'home-desktop-join')->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
    }
});

it('opens joining directly for landscape phones', function () {
    $page = visit('/', ['viewport' => ['width' => 844, 'height' => 390]])->withLocale('en-US');
    $page->assertVisible('#room-code')->assertVisible('[data-testid="host-room"]')->assertNoJavaScriptErrors();
    expect(Room::count())->toBe(0);
    expect($page->page()->evaluate('() => { const r = document.querySelector("[data-testid=host-room]").getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; }'))->toBeTrue();
});
