<?php

use App\Models\RoomPlayer;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

it('lets only the connected chief configure a session', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    enterRoom($room, 'Autre');
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 500])->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $token)
        ->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 500])
        ->assertOk()->assertJsonPath('room.pointTarget', 500);
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => null])
        ->assertOk()->assertJsonPath('room.pointTarget', null);
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => [500]])->assertUnprocessable();
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 0])->assertUnprocessable();
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 4294967296])->assertUnprocessable();
    RoomPlayer::findOrFail($chief)->update(['left_at' => now()]);
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => 100])->assertForbidden();
});

it('extends from the best actual score including departed players and rejects overflow', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    [$other] = enterRoom($room, 'Autre');
    RoomPlayer::findOrFail($other)->update(['score' => 1180, 'left_at' => now()]);
    $this->withCookie('plummo_player_'.$room->code, $token)
        ->patchJson('/rooms/'.$room->code.'/session', ['action' => 'extend', 'extra' => 500])
        ->assertOk()->assertJsonPath('room.pointTarget', 1680);
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'extend', 'extra' => 4294967295])->assertUnprocessable();
    expect($room->fresh()->point_target)->toBe(1680);
});

it('requires confirmation before resetting all scores while preserving identities', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room, 'Camille', []);
    RoomPlayer::findOrFail($chief)->update(['score' => 900]);
    $this->withCookie('plummo_player_'.$room->code, $token)
        ->patchJson('/rooms/'.$room->code.'/session', ['action' => 'restart', 'target' => 1000])->assertUnprocessable();
    $identity = RoomPlayer::findOrFail($chief)->identity_hash;
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'restart', 'target' => 1000, 'confirm' => true])
        ->assertOk()->assertJsonPath('me.score', 0)->assertJsonPath('me.name', 'Camille');
    expect(RoomPlayer::findOrFail($chief)->identity_hash)->toBe($identity)
        ->and($room->fresh()->owner_id)->toBe($chief);
});

it('publishes shared ranking positions and retains departed players with duplicate names', function () {
    $room = openRoom();
    [$one] = enterRoom($room);
    [$two] = enterRoom($room);
    [$three] = enterRoom($room);
    RoomPlayer::whereIn('id', [$one, $two])->update(['score' => 100]);
    RoomPlayer::findOrFail($two)->update(['left_at' => now()]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk()
        ->assertJsonPath('room.pointTarget', null)
        ->assertJsonPath('room.ranking.0.rank', 1)->assertJsonPath('room.ranking.1.rank', 1)
        ->assertJsonPath('room.ranking.2.rank', 3)->assertJsonPath('room.ranking.2.id', $three)
        ->assertJsonCount(2, 'room.players')->assertJsonCount(3, 'room.ranking');
});

it('creates new sessions without a score limit', function () {
    $room = app(RoomService::class)->create();
    expect($room->fresh()->point_target)->toBeNull();
});
