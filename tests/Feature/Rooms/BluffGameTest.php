<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\RoomPlayer;
use App\Models\Tag;
use App\Services\BonusClock;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(function () {
    $this->withCredentials();
});

function bluffSettings(): array
{
    $tag = Tag::create(['name' => 'Bluff']);
    $pack = Pack::create(['name' => 'Bluff']);
    $pack->tags()->sync([$tag->id]);
    foreach (range(1, 5) as $number) {
        $content = Content::create(['type' => 'bluff', 'published' => true, 'payload' => ['question' => 'Définition '.$number, 'answer' => 'Vérité', 'format' => 'definition']]);
        $content->tags()->sync([$tag->id]);
    }

    return ['type' => 'bluff', 'rounds' => 5, 'duration' => 60, 'packs' => [$pack->id]];
}

function bluffExpire($room): void
{
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);
    test()->getJson('/rooms/'.$room->code.'/state')->assertOk();
}

it('requires three players and valid settings then exposes only the question', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    $settings = bluffSettings();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', [...$settings, 'rounds' => 1])->assertUnprocessable();
    $view = $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated()->assertJsonPath('game.phase', 'writing')->json('game');
    expect($view['round']['entries'])->toBe([])->and(json_encode($view))->not->toContain('Vérité');
});

it('keeps drafts private and unsubmitted drafts out of voting and awards each vote once', function () {
    $room = openRoom();
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $ids = array_keys($tokens);
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $data = ['game_id' => Game::latest('id')->value('id'), 'round' => 1];
    foreach ($ids as $i => $id) {
        $this->withCookie('plummo_player_'.$room->code, $tokens[$id])->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'text' => $i === 0 ? 'Brouillon secret' : 'Bluff '.$i, 'submit' => $i > 0])->assertOk();
    }
    $this->withCookie('plummo_player_'.$room->code, $tokens[$ids[1]])->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.draft', 'Bluff 1');
    bluffExpire($room);
    $game = Game::latest('id')->firstOrFail();
    expect(array_column($game->state['round']['entries'], 'text'))->not->toContain('Brouillon secret');
    foreach (range(1, 3) as $i) {
        bluffExpire($room);
    }
    $game->refresh();
    expect($game->state['phase'])->toBe('voting');
    $truth = collect($game->state['round']['entries'])->firstWhere('correct', true)['id'];
    $own = collect($game->state['round']['entries'])->first(fn ($entry) => in_array($ids[1], $entry['authors'], true))['id'];
    $this->withCookie('plummo_player_'.$room->code, $tokens[$ids[1]])->postJson('/rooms/'.$room->code.'/bluff/vote', [...$data, 'choice' => $own])->assertForbidden();
    foreach ($ids as $id) {
        $response = $this->withCookie('plummo_player_'.$room->code, $tokens[$id])->postJson('/rooms/'.$room->code.'/bluff/vote', [...$data, 'choice' => $truth])->assertOk();
        if ($id !== end($ids)) {
            expect(array_column($response->json('game.round.entries'), 'correct'))->toBe([null, null, null]);
            $this->postJson('/rooms/'.$room->code.'/bluff/vote', [...$data, 'choice' => $truth])->assertConflict();
        }
    }
    expect($response->json('game.phase'))->toBe('reveal')->and((int) $room->players()->sum('score'))->toBe(195);
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect((int) $room->players()->sum('score'))->toBe(195);
});

it('groups duplicates and reserves a truth finder reward without a vote', function () {
    $room = openRoom();
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $ids = array_keys($tokens);
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $data = ['game_id' => Game::latest('id')->value('id'), 'round' => 1];
    foreach ($ids as $i => $id) {
        $view = $this->withCookie('plummo_player_'.$room->code, $tokens[$id])->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'text' => $i === 0 ? 'vérité!' : 'Avion', 'submit' => true])->assertOk();
        if ($i === 0) {
            expect($view->json('game.me.foundTruth'))->toBeFalse();
        }
    }
    $game = Game::latest('id')->firstOrFail();
    expect($game->state['round']['entries'])->toHaveCount(2);
    $this->withCookie('plummo_player_'.$room->code, $tokens[$ids[0]])->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.foundTruth', true);
    bluffExpire($room);
    bluffExpire($room);
    $truth = collect($game->fresh()->state['round']['entries'])->firstWhere('correct', true)['id'];
    $this->postJson('/rooms/'.$room->code.'/bluff/vote', [...$data, 'choice' => $truth])->assertForbidden();
    foreach (array_slice($ids, 1) as $id) {
        $this->withCookie('plummo_player_'.$room->code, $tokens[$id])->postJson('/rooms/'.$room->code.'/bluff/vote', [...$data, 'choice' => $truth])->assertOk();
    }
    expect(RoomPlayer::findOrFail($ids[0])->score)->toBe(65)->and((int) $room->players()->sum('score'))->toBe(195);
});

it('skips an empty vote and waits for enough players before a new round', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    [$third] = enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    bluffExpire($room);
    expect(Game::latest('id')->firstOrFail()->state['phase'])->toBe('reveal');
    RoomPlayer::findOrFail($third)->update(['left_at' => now()]);
    bluffExpire($room);
    expect(Game::latest('id')->firstOrFail()->state['phase'])->toBe('waiting');
    RoomPlayer::findOrFail($third)->update(['left_at' => null]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.phase', 'writing');
    expect((int) $room->players()->sum('score'))->toBe(0);
});

it('rejects empty submitted text and stale actions and freezes through pause', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $data = ['game_id' => Game::latest('id')->value('id'), 'round' => 1, 'text' => '...', 'submit' => true];
    $this->postJson('/rooms/'.$room->code.'/bluff/write', $data)->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'round' => 2, 'text' => 'Bluff'])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'text' => 'Bluff'])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    bluffExpire($room);
    $response = $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'writing');
    expect($response->json('game.deadline') - $response->json('serverTime'))->toBeGreaterThan(59);
});

it('keeps arrivals out of the current round and retains a departed player submission', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    [$third, $thirdToken] = enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $data = ['game_id' => Game::latest('id')->value('id'), 'round' => 1];
    $this->withCookie('plummo_player_'.$room->code, $thirdToken)->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'text' => 'Bluff conservé', 'submit' => true])->assertOk();
    [$late, $lateToken] = enterRoom($room, 'Nouveau');
    $this->withCookie('plummo_player_'.$room->code, $lateToken)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', false);
    $this->postJson('/rooms/'.$room->code.'/bluff/write', [...$data, 'text' => 'Intrus', 'submit' => true])->assertForbidden();
    RoomPlayer::findOrFail($third)->update(['left_at' => now()]);
    bluffExpire($room);
    expect(array_column(Game::latest('id')->firstOrFail()->state['round']['entries'], 'text'))->toContain('Bluff conservé');
    foreach (range(1, 4) as $phase) {
        bluffExpire($room);
    }
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.me.eligible', true);
});

it('uses voting duration for clock debt and limits effects to voting', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', [...bluffSettings(), 'duration' => 150, 'bonuses' => true])->assertCreated()->assertJsonPath('game.bonuses.canUse', false);
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['bonuses']['debt'] = 6;
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/bluff/write', ['game_id' => $game->id, 'round' => 1, 'text' => 'Un bluff', 'submit' => true])->assertOk();
    bluffExpire($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.canUse', false);
    bluffExpire($room);
    bluffExpire($room);
    $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'voting')->assertJsonPath('game.bonuses.canUse', true);
    expect($response->json('game.deadline') - $response->json('serverTime'))->toEqualWithDelta(19, 0.1);
    expect(app(BonusClock::class)->step($game))->toBe(6);
});

it('freezes catalogue text and pauses safely when remaining contents disappear', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $question = $game->state['round']['question'];
    Content::where('type', 'bluff')->update(['published' => false, 'payload' => ['question' => 'Modifiée', 'answer' => 'Nouvelle vérité', 'format' => 'definition']]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.question', $question);
    bluffExpire($room);
    bluffExpire($room);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'paused')->assertJsonPath('game.exhausted', true);
});

it('finishes at the session target and preserves definitive scores', function () {
    $room = openRoom();
    $room->update(['point_target' => 65]);
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', bluffSettings())->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/bluff/write', ['game_id' => Game::latest('id')->value('id'), 'round' => 1, 'text' => 'Vérité', 'submit' => true])->assertOk();
    bluffExpire($room);
    expect(RoomPlayer::findOrFail($id)->score)->toBe(65);
    bluffExpire($room);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'results')->assertJsonPath('game.targetReached', true);
    expect(Game::latest('id')->firstOrFail()->status)->toBe('finished')->and((int) $room->players()->sum('score'))->toBe(65);
});
