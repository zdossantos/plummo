<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;
use App\Models\Tag;
use Illuminate\Support\Str;

it('launches a collective bonus in one click while phone and screen stay inside their viewport', function () {
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
        $first->page()->locator('button:has-text("Start quiz")')->click(['noWaitAfter' => true]);
        $first->assertSee('Quiz · Question 1 / 5');
        expect(Game::where('room_id', $room->id)->sole()->settings['bonuses'])->toBeTrue();
        $first->assertPresent('.bonus-object');
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
        $screen->page()->evaluate('() => { window.bonusNotes = 0; const original = AudioContext.prototype.createOscillator; AudioContext.prototype.createOscillator = function(...args) { window.bonusNotes++; return original.apply(this, args); }; }');
        $screen->click('.sound-controls button')->assertAttribute('.sound-controls button', 'aria-pressed', 'true');
        $first->click('.bonus-object')->assertMissing('.bonus-object');
        $screen->page()->evaluate('() => new Promise(resolve => { const until = Date.now() + 4000; const check = () => window.bonusNotes > 0 || Date.now() >= until ? resolve() : setTimeout(check, 50); check(); })');
        $notes = $screen->page()->evaluate('() => window.bonusNotes');
        expect($notes)->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4);
        $screen->page()->evaluate('() => new Promise(resolve => setTimeout(resolve, 1200))');
        expect($screen->page()->evaluate('() => window.bonusNotes'))->toBe($notes);
        $second->assertPresent('.game-choices button');
        $screen->assertMissing('.bonus-pocket')->assertMissing('.page-deck');
        $first->screenshot(filename: 'bonus-phone');
        $screen->screenshot(filename: 'bonus-screen');
        $game = Game::where('room_id', $room->id)->sole();
        $player = $room->players()->where('name', 'Camille')->sole();
        $state = $game->state;
        $state['bonuses']['inventory'][$player->id] = [['id' => 'keep', 'kind' => 'bolt'], ['id' => 'replace', 'kind' => 'dice']];
        $state['bonuses']['pending'][$player->id] = ['id' => 'third', 'kind' => 'squatter'];
        $game->update(['state' => $state]);
        $first->refresh();
        $first->assertPresent('.bonus-pending');
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
});

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
        $first->assertSee('Prank bonuses')->click('label:has-text("Prank bonuses")')->click('button:has-text("Start drawing")');
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
        $first->assertPresent('polyline:nth-of-type(3)[stroke="#35236b"]');
        $first->page()->evaluate('() => { const board = document.querySelector("[data-testid=drawing-board]"); const r = board.getBoundingClientRect(); board.dispatchEvent(new PointerEvent("pointerup", {pointerId:1,bubbles:true,clientX:r.left+.8*r.width,clientY:r.top+.8*r.height})); }');
        $screen->assertPresent('polyline:nth-of-type(3)[stroke="#35236b"]');
        expect($game->fresh()->state['round']['canvas'])->toHaveCount(3);
        $first->assertNoJavaScriptErrors();
    } finally {
        $room->delete();
        Content::whereIn('id', $ids)->delete();
        $pack->delete();
        $tag->delete();
    }
});
