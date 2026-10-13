<?php

use App\Models\RoomPlayer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

function readySettings(): array
{
    return ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30, 'allow_repeats' => false, 'bonuses' => false];
}

it('accepts an incomplete selection while preparing but requires it before launch', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    foreach ([['type' => 'quiz', 'packs' => []], ['type' => 'mini_games', 'trials' => []]] as $selection) {
        $settings = [...$selection, 'rounds' => 5, 'duration' => 30];
        $this->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk()->assertJsonPath('room.gameSetup.type', $selection['type']);
        $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable()->assertJsonValidationErrors($selection['type'] === 'quiz' ? 'packs' : 'trials');
    }
    $this->assertDatabaseCount('games', 0);
});

it('refuses a direct launch without confirmations and creates no game or history', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', readySettings())->assertUnprocessable()->assertJsonValidationErrors('ready');
    $this->assertDatabaseCount('games', 0);
    $this->assertDatabaseCount('room_content_history', 0);
});

it('requires every connected player including the chief and allows withdrawing confirmation', function () {
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Alex');
    $settings = readySettings();
    $setup = $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk();
    $revision = $setup->json('room.setupRevision');
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk()->assertJsonPath('me.ready', true);
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable()->assertJsonValidationErrors('ready');
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => false, 'revision' => $revision])->assertOk()->assertJsonPath('me.ready', false);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated();
    expect(RoomPlayer::findOrFail($chief)->ready_revision)->toBeNull()->and(RoomPlayer::findOrFail($other)->ready_revision)->toBeNull();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertConflict();
});

it('invalidates confirmations when settings change and rejects stale or mismatched requests', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $settings = readySettings();
    $setup = $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk();
    $revision = $setup->json('room.setupRevision');
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk()->assertJsonPath('me.ready', true)->assertJsonPath('room.setupRevision', $revision);
    $this->postJson('/rooms/'.$room->code.'/games', [...$settings, 'duration' => 40])->assertUnprocessable()->assertJsonValidationErrors('ready');
    $changed = $this->postJson('/rooms/'.$room->code.'/game-setup', [...$settings, 'duration' => 40])->assertOk()->assertJsonPath('me.ready', false);
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $changed->json('room.setupRevision')])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    $this->assertDatabaseCount('games', 0);
});

it('requires new arrivals and returning players while departures stop blocking', function () {
    $this->freezeTime();
    $room = openRoom();
    [, $one] = enterRoom($room);
    $settings = readySettings();
    $setup = $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk();
    $revision = $setup->json('room.setupRevision');
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    [$other, $two] = enterRoom($room, 'Alex');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/return')->assertOk()->assertJsonPath('me.ready', false);
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $this->travel(10)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/presence')->assertOk()->assertJsonPath('me.ready', true);
    $this->travel(6)->seconds();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect(RoomPlayer::findOrFail($other)->ready_revision)->toBeNull();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated();
});

it('protects configuration and readiness from unauthorized or invalid participants', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    $settings = readySettings();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, '')->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => 0])->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => 0])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game-setup', [...$settings, 'duration' => 0])->assertUnprocessable();
});

it('clears confirmations when the session target changes or scores restart', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $settings = readySettings();
    $setup = $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-setup', $settings)->assertOk();
    $revision = $setup->json('room.setupRevision');
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertOk();
    $changed = $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 1000])->assertOk()->assertJsonPath('me.ready', false);
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $revision])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/ready', ['ready' => true, 'revision' => $changed->json('room.setupRevision')])->assertOk();
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 1000])->assertOk()->assertJsonPath('me.ready', true);
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'restart', 'target' => 1000, 'confirm' => true])->assertOk()->assertJsonPath('me.ready', false);
});
