<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Room;
use Pest\Browser\Support\Screenshot;

it('animates a confirmed response once and suppresses old gestures after reconnect and motion reduction', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $pack = Pack::findOrFail(quizPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/', ['viewport' => ['width' => 1366, 'height' => 768]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $phone = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $other = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $phone->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->fill('game-rounds', '5');
        $phone->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Quiz · Question 1 / 5');
        $phone->assertPresent('.phone-game-plummo [data-plummo-part="iris-left"]');
        $screen->assertSee('Quiz · Question 1 / 5')->assertSee('Camille')->assertSee('Alex');
        copy(Screenshot::path($phone->page()->screenshot(true, 'plummo-motion-phone')), '/tmp/plummo-motion-phone.png');
        copy(Screenshot::path($screen->page()->screenshot(true, 'plummo-motion-screen')), '/tmp/plummo-motion-screen.png');
        expect($phone->page()->evaluate('() => document.querySelector(".viewport-footer").getBoundingClientRect().height'))->toBeLessThanOrEqual(48);
        $phone->page()->evaluate('() => document.querySelector(".viewport-shell").setAttribute("data-compact", "true")');
        expect($phone->page()->evaluate('() => document.querySelector(".viewport-footer").getBoundingClientRect().height'))->toBe(0);
        $phone->page()->evaluate('() => document.querySelector(".viewport-shell").removeAttribute("data-compact")');
        $phone->page()->evaluate('() => { window.plummoGestures = []; const avatar = document.querySelector(".phone-game-plummo"); new MutationObserver(() => { const motion = avatar.dataset.plummoMotion; if (motion && motion !== "idle") window.plummoGestures.push(motion); }).observe(avatar, { attributes: true, attributeFilter: ["data-plummo-motion"] }); }');
        $phone->page()->locator('.game-choice > button:first-child')->nth(1)->click(['noWaitAfter' => true]);
        $phone->assertSee('Answer saved');
        $phone->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 2600))');
        expect($phone->page()->evaluate('() => window.plummoGestures'))->toBe(['answer']);
        expect($phone->page()->evaluate('() => document.querySelector(".phone-game-plummo").dataset.plummoMotion'))->toBe('idle');
        expect($phone->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $phone->refresh()->assertSee('Answer saved');
        expect($phone->page()->evaluate('() => document.querySelector(".phone-game-plummo").dataset.plummoMotion'))->toBe('idle');
        // Re-mount from the actual room snapshot with the browser preference set.
        $reduced = visit('/join/'.$room->code, ['reducedMotion' => 'reduce', 'viewport' => ['width' => 390, 'height' => 844]])->withLocale('en-US')->fill('player-name', 'Robin')->click('Continue')->click('Enter the room');
        expect($reduced->page()->evaluate('() => document.getAnimations().filter(a => a.effect?.target?.hasAttribute("data-plummo-part")).length'))->toBe(0);
        expect($screen->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $phone->assertNoJavaScriptErrors();
        $other->assertNoJavaScriptErrors();
        $reduced->assertNoJavaScriptErrors();
    } finally {
        // Stop polling before removing the owned fixture, including on an assertion failure.
        foreach (array_filter([$phone ?? null, $other ?? null, $reduced ?? null, $screen]) as $browser) {
            $browser->page()->close();
        }
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
