<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Execution;
use Pest\Browser\Playwright\Playwright;

it('plays looping audio on the screen and pauses it while phones select eight choices', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    Storage::fake('local');
    // Pest trims streamed bodies: keep the final PCM byte non-whitespace.
    $samples = str_repeat("\x01\x01", 8000);
    $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    Storage::disk('local')->put('audio/test.wav', $wav);
    $pack = Pack::findOrFail(blindPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    // Model WebKit's per-element permission, granted only during the actual click.
    $screen->page()->evaluate('() => {
        window.nativePlay = HTMLMediaElement.prototype.play;
        const unlocked = new WeakSet();
        let gesture = false;
        document.addEventListener("click", event => { gesture = event.isTrusted; setTimeout(() => gesture = false, 0); }, true);
        HTMLMediaElement.prototype.play = function () {
            if (gesture) unlocked.add(this);
            if (!unlocked.has(this)) return Promise.reject(new DOMException("Autoplay blocked", "NotAllowedError"));
            return window.nativePlay.call(this);
        };
    }');
    $room = Room::latest('id')->firstOrFail();
    try {
        $phone = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')
            ->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $phone->click('button[aria-label="Controls"]')->click('Session')->assertSee('No point limit')->click('label:has-text("No point limit")')->fill('session-target', '50')->click('Save target')->assertSee('Target: 50 points');
        $phone->click('button[aria-label="Play"]')->assertSee('Mini-game');
        $phone->click('[data-choose-game]')->click('[data-game-option="blind_test"]');
        $phone->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")')->assertSee('8 unseen clips')->fill('game-rounds', '5');
        $phone->assertDisabled('button:has-text("Start blind test")')->assertSee('Press Enable sound on the big screen');
        $screen->click('button[aria-label="Enable sound"]')->assertPresent('.sound-controls button[aria-pressed="true"]');
        expect($screen->page()->evaluate('() => { window.unlockedAudio = document.querySelector("audio"); return !!window.unlockedAudio; }'))->toBeTrue();
        $phone->assertEnabled('button:has-text("Start blind test")');
        $phone->page()->locator('button:has-text("Start blind test")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Blind test · Clip 1 / 5');
        $screen->assertSee('Blind test · Clip 1 / 5')->assertDontSee('Your browser blocks automatic sound.')->assertMissing('.game-play button');
        expect($screen->page()->evaluate('() => document.querySelector("audio") === window.unlockedAudio && !window.unlockedAudio.paused && !window.unlockedAudio.muted'))->toBeTrue();
        $phone->page()->locator('button:has-text("Pause")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Game paused');
        $screen->assertSee('Game paused');
        $phone->page()->locator('button:has-text("Resume")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Resuming in…');
        Playwright::usingTimeout(10000, fn () => $screen->assertSee('Which song is playing?'));
        $screen->assertDontSee('Your browser blocks automatic sound.');
        Playwright::usingTimeout(10000, fn () => $phone->assertSee('Which song is playing?'));
        expect($screen->page()->evaluate('document.querySelector("audio").loop && !document.querySelector("audio").paused'))->toBeTrue();
        expect($phone->page()->evaluate('document.querySelectorAll(".game-choice > button:first-child").length'))->toBe(8);
        expect($phone->page()->evaluate('document.querySelector("audio") === null'))->toBeTrue();
        $phone->page()->locator('button:has-text("Pause")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Game paused');
        $screen->assertSee('Game paused');
        $position = $screen->page()->evaluate('document.querySelector("audio").currentTime');
        expect($screen->page()->evaluate('document.querySelector("audio").paused'))->toBeTrue();
        $screen->page()->evaluate('() => { window.pausedAudio = document.querySelector("audio"); }');
        $screen->assertMissing('.game-play button')->assertMissing('.page-controls');
        expect($screen->page()->evaluate('document.querySelector("audio") === window.pausedAudio'))->toBeTrue();
        $phone->page()->locator('button:has-text("Resume")')->click(['noWaitAfter' => true]);
        $phone->assertSee('Resuming in…');
        $screen->assertSee('Resuming in…');
        expect($screen->page()->evaluate('document.querySelector("audio").paused'))->toBeTrue();
        expect($screen->page()->evaluate('document.querySelector("audio").currentTime'))->toBe($position);
        Playwright::usingTimeout(10000, fn () => $screen->assertSee('Which song is playing?'));
        expect($screen->page()->evaluate('!document.querySelector("audio").paused'))->toBeTrue();
        $screen->page()->evaluate('() => { window.dispatchEvent(new Event("offline")); }');
        expect($screen->page()->evaluate('document.querySelector("audio").paused'))->toBeTrue();
        $screen->page()->evaluate('() => { window.dispatchEvent(new Event("online")); }');
        $screen->assertDontSee('Connection interrupted. Trying again…');
        $game = Game::where('room_id', $room->id)->sole();
        $choice = $game->state['round']['payload']['correct'];
        $phone->assertSee('Which song is playing?');
        $phone->page()->locator('.game-choice > button:first-child')->nth($choice)->click(['noWaitAfter' => true]);
        $phone->assertSee('65 points for this question');
        $screen->assertSee('Mini-game leaderboard')->assertSee('Well done, Camille!');
        $phone->assertSee('Mini-game leaderboard');
        Storage::disk('local')->assertMissing($game->state['round']['payload']['audio_path']);
        $phone->page()->locator('button:has-text("Back to lobby")')->click(['noWaitAfter' => true]);
        $screen->assertSee('Everyone plays.')->assertSee('65 Points')->assertNoJavaScriptErrors();
        $screen->click('button[aria-label="Mute sound"]')->assertPresent('.sound-controls button[aria-pressed="false"]')->click('button[aria-label="Enable sound"]')->assertPresent('.sound-controls button[aria-pressed="true"]');
        Execution::instance()->waitForExpectation(function () use ($screen) {
            expect($screen->page()->evaluate('() => document.querySelector("audio") === window.unlockedAudio && new URL(window.unlockedAudio.src).pathname === "/audio/plummo-ambiance.mp3" && !window.unlockedAudio.loop && window.unlockedAudio.paused'))->toBeTrue();
        });
        $phone->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
