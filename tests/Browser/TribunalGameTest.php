<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;
use App\Models\Tag;
use App\Services\GameEngine;
use Illuminate\Support\Str;

beforeEach(function () {
    app()->terminating(function () {
        app('cookie')->flushQueuedCookies();
        app('session.store')->flush();
    });
    test()->withVite();
    pest()->browser()->timeout(15000);
});
afterEach(fn () => pest()->browser()->timeout(5000));

it('keeps the tribunal passive on TV and playable without scroll on compact phones', function (int $width, int $height, bool $phone) {
    $tag = Tag::create(['name' => 'Tribunal browser '.Str::uuid()]);
    $pack = Pack::create(['name' => $tag->name]);
    $pack->tags()->sync([$tag->id]);
    foreach (range(1, 3) as $number) {
        $content = Content::create(['type' => 'tribunal', 'published' => true, 'payload' => ['question' => 'Should Plummos get holidays? '.$number]]);
        $content->tags()->sync([$tag->id]);
    }
    $screen = visit('/host', ['viewport' => ['width' => 1280, 'height' => 720]])->withLocale('en-US')->assertSee('Everyone plays.');
    $room = Room::latest('id')->firstOrFail();
    try {
        $pages = [];
        foreach (['Camille', 'Alex', 'Morgan'] as $name) {
            $page = visit('/join/'.$room->code, ['viewport' => ['width' => $width, 'height' => $height]])->withLocale('en-US')->fill('player-name', $name)->click('Continue')->click('Enter the room')->assertSee($name);
            $pages[$room->players()->latest('id')->firstOrFail()->id] = $page;
        }
        reset($pages)->refresh()->click('[data-choose-game]')->click('[data-game-option="tribunal"]')->assertSee('Prepare the tribunal')->assertSee('Content packs')->click('[data-choose-packs]')->check($pack->name)->click('Close')->assertDontSee('{count}')->click('Open the tribunal')->assertPresent('[data-testid="tribunal-play"]');
        $game = Game::where('room_id', $room->id)->latest('id')->firstOrFail();
        $jury = array_values(array_diff(array_keys($pages), $game->state['round']['advocates']))[0];
        $page = $phone ? $pages[$jury] : $screen;
        foreach (['preparing', 'speaking_first', 'speaking_second', 'voting', 'reveal'] as $phase) {
            $state = $game->fresh()->state;
            $state['phase'] = $phase;
            $state['deadline'] = app(GameEngine::class)->time() + 150;
            if ($phase === 'reveal') {
                $state['round']['votes'] = [$jury => 0];
                $state['round']['awards'] = [$state['round']['advocates'][0] => 65];
            }
            app(GameEngine::class)->save($game, ['state' => $state]);
            $page->refresh()->assertPresent('[data-testid="tribunal-play"]')->assertMissing('.page-controls')->assertDontSee(':number')->assertDontSee(':name');
            $page->page()->evaluate('() => document.fonts.ready');
            expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight'))->toBeTrue();
            expect($page->page()->evaluate('() => { const stage=document.querySelector(".tribunal-stage").getBoundingClientRect(); return stage.height > 80 && stage.bottom <= innerHeight; }'))->toBeTrue();
            expect($page->page()->evaluate('() => [...document.querySelectorAll(".tribunal-question,.tribunal-lectern,.tribunal-directive,.tribunal-ballot")].every(e => { const r=e.getBoundingClientRect(); return e.scrollHeight <= e.clientHeight+1 && r.bottom <= innerHeight && r.left>=0 && r.right<=innerWidth; })'))->toBeTrue();
            if ($phone) {
                expect($page->page()->evaluate('() => [...document.querySelectorAll(".tribunal-character > svg:not(.tribunal-mic)")].every(e => e.getBoundingClientRect().height >= 48)'))->toBeTrue();
            }
            if (! $phone) {
                $page->assertMissing('[data-tribunal-choice]')->assertMissing('[data-tribunal-finish]');
            }
            if ($phone && $phase === 'voting') {
                $page->assertEnabled('[data-tribunal-choice="0"]');
            }
            $page->assertNoJavaScriptErrors();
            if ($phase === 'speaking_first' || $phase === 'voting') {
                $page->screenshot(filename: 'tribunal-'.$phase.'-'.($phone ? $width : 'tv'));
            }
        }
        if ($phone) {
            $state = $game->fresh()->state;
            $state['phase'] = 'speaking_first';
            $state['round']['votes'] = [];
            $state['round']['awards'] = [];
            app(GameEngine::class)->save($game, ['state' => $state]);
            $pages[$state['round']['advocates'][0]]->refresh()->assertPresent('[data-tribunal-finish]')->click('[data-tribunal-finish]')->assertMissing('[data-tribunal-finish]');
            $pages[$state['round']['advocates'][1]]->refresh()->assertPresent('[data-tribunal-finish]')->click('[data-tribunal-finish]')->assertMissing('[data-tribunal-finish]');
            $page->refresh()->click('[data-tribunal-choice="0"]')->assertSee('wins the debate');
            expect($game->fresh()->state['round']['awards'][$state['round']['advocates'][0]])->toBe(65);
        }
    } finally {
        $room->delete();
        Content::whereHas('tags', fn ($query) => $query->whereKey($tag->id))->delete();
        $pack->delete();
        $tag->delete();
    }
})->with([[390, 844, false], [390, 844, true], [360, 640, true]]);
