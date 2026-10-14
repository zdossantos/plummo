<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;
use App\Models\Tag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pest\Browser\Execution;

pest()->browser()->timeout(15000);

it('launches a collective bonus in one click while phone and screen stay inside their viewport', function (bool $reduced) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $tag = Tag::create(['name' => 'Bonus '.Str::uuid()]);
    $pack = Pack::create(['name' => 'Bonus quiz']);
    $pack->tags()->sync([$tag->id]);
    foreach (range(1, 5) as $number) {
        $content = Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => ['question' => 'Question '.$number, 'choices' => ['A', 'B', 'C', 'D'], 'correct' => 0]]);
        $content->tags()->sync([$tag->id]);
    }
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/', ['reducedMotion' => $reduced ? 'reduce' : 'no-preference', 'viewport' => ['width' => $reduced ? 1280 : 1920, 'height' => $reduced ? 720 : 1080]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $second = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")');
        $first->assertSee('Prank bonuses');
        expect($first->page()->evaluate('() => { const label = [...document.querySelectorAll(".setup-details label")].find(e => e.textContent.includes("Prank bonuses")); const r = label.getBoundingClientRect(); return label.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2)); }'))->toBeTrue();
        $first->click('label:has-text("Prank bonuses")');
        $first->fill('game-rounds', '5')->fill('game-duration', '60');
        confirmReadyPhone($first, $room);
        confirmReadyPhone($second, $room);
        $first->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $first->assertSee('Quiz · Question 1 / 5');
        expect(Game::where('room_id', $room->id)->sole()->settings['bonuses'])->toBeTrue();
        $game = Game::where('room_id', $room->id)->sole();
        $player = $room->players()->where('name', 'Camille')->sole();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'launch-test', 'kind' => 'bolt']];
        $game->update(['state' => $state]);
        $first->refresh()->assertPresent('.bonus-object');
        $second->assertPresent('.bonus-object');
        foreach ([$first, $second, $screen] as $page) {
            expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
            $page->assertNoJavaScriptErrors();
        }
        $first->click('.bonus-info')->assertPresent('.bonus-tooltip');
        $first->page()->setViewportSize(390, 360);
        $first->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 200))');
        $first->assertVisible('.bonus-object');
        expect($first->page()->evaluate('() => { const r = document.querySelector(".bonus-object").getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; }'))->toBeTrue();
        $first->page()->setViewportSize(390, 664);
        $screen->assertSee('Quiz · Question 1 / 5');
        $screen->page()->evaluate('() => { window.bonusNotes = 0; const original = AudioBufferSourceNode.prototype.start; AudioBufferSourceNode.prototype.start = function(...args) { if (!this.loop) window.bonusNotes++; return original.apply(this, args); }; }');
        $screen->click('.sound-controls button')->assertAttribute('.sound-controls button', 'aria-pressed', 'true');
        $screen->page()->evaluate('() => { window.launchIds = new Set(); new MutationObserver(() => document.querySelectorAll("[data-testid=bonus-launch]").forEach(item => window.launchIds.add(item.dataset.launchId))).observe(document.body, {subtree:true,childList:true}); }');
        $first->click('.bonus-object')->assertMissing('.bonus-object');
        $screen->assertVisible('[data-testid="bonus-launch"]')->assertSee('Camille');
        $screen->assertPresent('[data-testid="bonus-impact"]');
        expect($screen->page()->evaluate('() => getComputedStyle(document.querySelector(".bonus-impact")).pointerEvents'))->toBe('none');
        $first->assertMissing('[data-testid="bonus-impact"]');
        expect($screen->page()->evaluate('() => { const r = document.querySelector(".bonus-impact").getBoundingClientRect(); return Math.abs(r.x) < 2 && Math.abs(r.y) < 2 && Math.abs(r.width - innerWidth) < 2 && Math.abs(r.height - innerHeight) < 2; }'))->toBeTrue();
        expect($screen->page()->evaluate('() => getComputedStyle(document.querySelector(".bonus-launch-layer")).pointerEvents'))->toBe('none');
        if ($reduced) {
            expect($screen->page()->evaluate('() => [".bonus-launch", ".bonus-launch-projectile"].every(selector => getComputedStyle(document.querySelector(selector)).animationName === "none")'))->toBeTrue();
            expect($screen->page()->evaluate('() => getComputedStyle(document.querySelector(".bonus-stage-scene")).display'))->toBe('none');
            $screen->assertVisible('.bonus-stage-reduced');
        } else {
            $screen->assertVisible('.vfx-lightning');
            expect($screen->page()->evaluate('() => document.querySelector(".vfx-lightning").getBoundingClientRect().height'))->toBeGreaterThan(600);
        }
        $screen->screenshot(filename: 'bonus-launch-'.($reduced ? 'reduced' : 'animated'));

        $screen->page()->evaluate('() => new Promise(resolve => { const until = Date.now() + 4000; const check = () => window.bonusNotes > 0 || Date.now() >= until ? resolve() : setTimeout(check, 50); check(); })');
        $notes = $screen->page()->evaluate('() => window.bonusNotes');
        expect($notes)->toBe(1);
        $screen->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 1200))');
        expect($screen->page()->evaluate('() => window.bonusNotes'))->toBe($notes);
        expect($screen->page()->evaluate('() => window.launchIds.size'))->toBe(1);
        $screen->refresh()->assertMissing('[data-testid="bonus-launch"]');
        $second->assertPresent('.game-choices button');
        $screen->assertMissing('.bonus-pocket')->assertMissing('.page-deck');
        $first->screenshot(filename: 'bonus-phone');
        $screen->screenshot(filename: 'bonus-screen');
        $game = Game::where('room_id', $room->id)->sole();
        $player = $room->players()->where('name', 'Camille')->sole();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'keep', 'kind' => 'shield'], ['id' => 'replace', 'kind' => 'dice']];
        $state['bonuses']['pending'][$player->id] = ['id' => 'third', 'kind' => 'squatter'];
        $game->update(['state' => $state]);
        $first->refresh();
        $first->assertPresent('.bonus-pending');
        $first->assertPresent('.bonus-helpful')->assertPresent('.bonus-prank');
        $first->page()->evaluate('async () => { await Promise.all([...document.querySelectorAll(".bonus-pocket .bonus-icon")].map(img => img.decode())); }');
        expect($first->page()->evaluate('() => [...document.querySelectorAll(".bonus-pocket .bonus-icon")].every(img => img.complete && img.naturalWidth > 0 && img.getAttribute("src").startsWith("/bonuses/"))'))->toBeTrue();
        $first->screenshot(filename: 'bonus-illustrated-pocket');
        $first->page()->setViewportSize(320, 664);
        $first->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 200))');
        expect($first->page()->evaluate('() => [...document.querySelectorAll(".bonus-object, .bonus-pending, .phone-game-plummo")].every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight; })'))->toBeTrue();
        $first->page()->setViewportSize(390, 664);
        $first->click('.bonus-pending')->assertSee('A new object!');
        expect($first->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $first->page()->setViewportSize(390, 360);
        $first->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 600))');
        expect($first->page()->evaluate('() => [...document.querySelectorAll(".bonus-replace-drawer button")].every(button => { const rect = button.getBoundingClientRect(); const drawer = button.closest(".bonus-replace-drawer").getBoundingClientRect(); return rect.top >= Math.max(0, drawer.top) && rect.bottom <= Math.min(innerHeight, drawer.bottom) && rect.left >= 0 && rect.right <= innerWidth; })'))->toBeTrue();
        $first->page()->locator('button:has-text("Replace Tricky die")')->click(['noWaitAfter' => true]);
        $first->assertMissing('.bonus-pending');
        expect($game->fresh()->state['bonuses']['inventory'][$player->id][1]['id'])->toBe('third');
        expect($game->fresh()->state['bonuses']['inventory'][$player->id][0]['id'])->toBe('keep');
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
})->with([false, true]);

it('changes a held drawing stroke at paint activation and expiry without losing previous segments', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $tag = Tag::create(['name' => 'Paint '.Str::uuid()]);
    $pack = Pack::create(['name' => 'Paint drawing']);
    $pack->tags()->sync([$tag->id]);
    foreach (range(1, 6) as $number) {
        $content = Content::create(['type' => ContentType::Drawing, 'published' => true, 'payload' => ['word' => 'Nuage '.$number]]);
        $content->tags()->sync([$tag->id]);
    }
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room')->assertSee('Prepare a quiz');
        $second = visit('/join/'.$room->code)->on()->mobile()->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room')->assertSee('Alex');
        $first->click('[data-choose-game]')->click('[data-game-option="drawing"]');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")');
        $first->assertSee('Prank bonuses')->click('label:has-text("Prank bonuses")');
        confirmReadyPhone($first, $room);
        confirmReadyPhone($second, $room);
        $first->click('button:has-text("Start drawing")');
        $first->assertSee('Choose a word');
        $first->page()->locator('[data-testid="drawing-word"]')->first()->click(['noWaitAfter' => true]);
        $first->assertPresent('[data-testid="drawing-board"]');
        $game = Game::where('room_id', $room->id)->sole();
        $state = $game->state;
        $other = $room->players()->where('name', 'Alex')->sole()->id;
        $state['bonuses']['inventory'][$other] = [['id' => 'paint-test', 'kind' => 'paint']];
        $game->update(['state' => $state]);
        $first->page()->evaluate('() => { const board = document.querySelector("[data-testid=drawing-board]"); const r = board.getBoundingClientRect(); board.dispatchEvent(new PointerEvent("pointerdown", {pointerId:1,bubbles:true,clientX:r.left+.1*r.width,clientY:r.top+.1*r.height})); }');
        $screen->assertPresent('polyline[stroke="#35236b"]');
        $second->assertPresent('[data-bonus="paint"]')->click('[data-bonus="paint"]');
        $first->assertPresent('polyline[stroke="#f05a78"]');
        $first->page()->evaluate('() => { const board = document.querySelector("[data-testid=drawing-board]"); const r = board.getBoundingClientRect(); board.dispatchEvent(new PointerEvent("pointermove", {pointerId:1,bubbles:true,clientX:r.left+.5*r.width,clientY:r.top+.5*r.height})); }');
        $screen->assertPresent('polyline[stroke="#f05a78"]');
        // Poll until the effect expires, while the same pointer remains held.
        $first->assertPresent('[data-tool="brush"]:nth-child(3) polyline[stroke="#35236b"]');
        $first->page()->evaluate('() => { const board = document.querySelector("[data-testid=drawing-board]"); const r = board.getBoundingClientRect(); board.dispatchEvent(new PointerEvent("pointerup", {pointerId:1,bubbles:true,clientX:r.left+.8*r.width,clientY:r.top+.8*r.height})); }');
        $screen->assertPresent('[data-tool="brush"]:nth-child(3) polyline[stroke="#35236b"]');
        expect($game->fresh()->state['round']['canvas'])->toHaveCount(3);
        $first->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});

it('keeps collective overlays usable and accepts only one of two simultaneous launches', function (string $kind) {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    $tag = Tag::create(['name' => 'Bonus '.Str::uuid()]);
    $pack = Pack::create(['name' => 'Bonus quiz']);
    $pack->tags()->sync([$tag->id]);
    foreach (range(1, 5) as $number) {
        $content = Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => ['question' => 'Question '.$number, 'choices' => ['A', 'B', 'C', 'D'], 'correct' => 0]]);
        $content->tags()->sync([$tag->id]);
    }
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/', ['viewport' => ['width' => 1920, 'height' => 1080]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $first = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $second = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")');
        $first->assertSee('Prank bonuses')->click('label:has-text("Prank bonuses")');
        $first->fill('game-rounds', '5')->fill('game-duration', '60');
        confirmReadyPhone($first, $room);
        confirmReadyPhone($second, $room);
        $first->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $first->assertSee('Quiz · Question 1 / 5');
        expect(Game::where('room_id', $room->id)->sole()->settings['bonuses'])->toBeTrue();
        $first->assertPresent('.bonus-object');
        $second->assertPresent('.bonus-object');

        $game = Game::where('room_id', $room->id)->sole();
        $player = $room->players()->where('name', 'Camille')->sole();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'parallel-one', 'kind' => $kind], ['id' => 'parallel-two', 'kind' => $kind]];
        $game->update(['state' => $state]);
        $first->refresh()->assertPresent('.bonus-object');
        $statuses = $first->page()->evaluate('async ({ code, gameId }) => Promise.all(["parallel-one", "parallel-two"].map(async itemId => { const response = await fetch(`/rooms/${code}/bonuses`, { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]").content }, body: JSON.stringify({ game_id: gameId, round: 1, item_id: itemId }) }); return response.status; }))', ['code' => $room->code, 'gameId' => $game->id]);
        sort($statuses);
        expect($statuses)->toBe([200, 409]);
        $bonus = $game->fresh()->state['bonuses'];
        expect($bonus['inventory'][$player->id])->toHaveCount(1);
        expect($bonus['launches'])->toHaveCount(1);
        expect($bonus['used'])->toBe([$player->id]);
        $selector = match ($kind) {
            'bolt' => '.bonus-blackout', 'squatter' => '.bonus-squatter', 'curtain' => '.answer-curtain', 'bubbles' => '.bonus-bubbles', 'mystery' => '.bonus-mystery', 'alphabet' => '.answer-upside', default => '.game-choices'
        };
        $second->assertPresent($selector);
        $first->assertMissing('.bonus-blackout')->assertMissing('.bonus-squatter');
        $second->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 300))');
        if (in_array($kind, ['bolt', 'squatter'], true)) {
            expect($second->page()->evaluate('(selector) => getComputedStyle(document.querySelector(selector)).pointerEvents', $selector))->toBe('none');
        }
        if ($kind === 'squatter') {
            $second->page()->setViewportSize(320, 664);
            expect($second->page()->evaluate('() => parseFloat(getComputedStyle(document.querySelector(".bonus-squatter")).width)'))->toBeGreaterThanOrEqual(140);
            expect($second->page()->evaluate('() => getComputedStyle(document.querySelector(".bonus-squatter")).animationDuration'))->toBe('8s');
            expect($second->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        }
        if ($kind === 'bubbles') {
            foreach (range(1, 6) as $unused) {
                // Moving bubbles deliberately never settle; skip Playwright's stability wait.
                $second->page()->locator('.bonus-bubbles button')->first()->click(['force' => true, 'noWaitAfter' => true]);
            }
            $second->assertMissing('.bonus-bubbles button');
        }
        if ($kind === 'mystery') {
            $second->assertPresent('.bonus-object.bonus-mystery[aria-label*="Surprise!"]')->assertMissing('.bonus-info');
        }
        if (in_array($kind, ['curtain', 'bubbles', 'alphabet'], true)) {
            $second->screenshot(filename: 'bonus-catalogue-'.$kind);
            expect($second->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        }
        $correct = $game->fresh()->state['round']['payload']['correct'];
        if ($kind === 'curtain') {
            $second->page()->locator('.game-choices button')->filter(['hasText' => ['A', 'B', 'C', 'D'][$correct]])->first()->click(['noWaitAfter' => true]);
            $second->assertPresent('.answer-curtain');
            expect($game->fresh()->state['round']['answers'])->toBeEmpty();
            $second->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 1500))');
            expect($second->page()->evaluate('() => document.querySelectorAll(".answer-curtain").length'))->toBe(3);
        }
        $second->page()->locator('.game-choices button')->filter(['hasText' => ['A', 'B', 'C', 'D'][$correct]])->first()->click(['noWaitAfter' => true]);
        $second->assertNoJavaScriptErrors();
        expect($game->fresh()->state['round']['answers'][$room->players()->where('name', 'Alex')->sole()->id]['choice'])->toBe($correct);
        $screen->assertMissing('.bonus-pocket')->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
})->with(['bolt', 'dice', 'squatter', 'curtain', 'bubbles', 'mystery', 'alphabet']);

it('temporarily hides artists on opponents phones and restores all eight answers', function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    Storage::fake('local');
    Storage::disk('local')->put('audio/test.wav', 'prepared audio');
    $pack = Pack::findOrFail(blindPack());
    $tag = $pack->tags()->sole();
    $ids = $tag->contents()->pluck('contents.id');
    $screen = visit('/', ['viewport' => ['width' => 1920, 'height' => 1080]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $screen->click('.sound-controls button')->assertAttribute('.sound-controls button', 'aria-pressed', 'true');
        $first = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Camille')->click('Continue')->click('Enter the room');
        $second = visit('/join/'.$room->code, ['viewport' => ['width' => 390, 'height' => 664]])->withLocale('en-US')->fill('player-name', 'Alex')->click('Continue')->click('Enter the room');
        $first->click('[data-choose-game]')->click('[data-game-option="blind_test"]');
        $first->click('[data-choose-packs]')->click('input[type="checkbox"][value="'.$pack->id.'"]')->click('[data-slot="drawer-content"] button:has-text("Close")');
        $first->click('label:has-text("Prank bonuses")')->fill('game-rounds', '5')->fill('game-duration', '60');
        confirmReadyPhone($first, $room);
        confirmReadyPhone($second, $room);
        $first->page()->locator('button:has-text("Start blind test")')->click(['noWaitAfter' => true]);
        $first->assertSee('Blind test · Clip 1 / 5');
        $second->assertPresent('.game-choices .text-summary');
        $game = Game::where('room_id', $room->id)->sole();
        $player = $room->players()->where('name', 'Camille')->sole();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'hide-artists', 'kind' => 'artist']];
        $game->update(['state' => $state]);
        $first->refresh()->assertPresent('.bonus-object')->click('.bonus-object');
        Execution::instance()->waitForExpectation(function () use ($second) {
            $answers = $second->page()->evaluate('() => [...document.querySelectorAll(".game-choices .text-summary")].map(node => node.textContent)');
            expect($answers)->toHaveCount(8);
            foreach ($answers as $answer) {
                expect($answer)->not->toContain(' — ');
            }
        });
        $ownAnswers = $first->page()->evaluate('() => [...document.querySelectorAll(".game-choices .text-summary")].map(node => node.textContent)');
        foreach ($ownAnswers as $answer) {
            expect($answer)->toContain(' — ');
        }
        expect($second->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        Execution::instance()->waitForExpectation(function () use ($second) {
            $restored = $second->page()->evaluate('() => [...document.querySelectorAll(".game-choices .text-summary")].map(node => node.textContent)');
            expect($restored)->toHaveCount(8);
            foreach ($restored as $answer) {
                expect($answer)->toContain(' — ');
            }
        });
        $second->assertNoJavaScriptErrors();
        $screen->assertMissing('.bonus-pocket')->assertMissing('.page-controls');
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
