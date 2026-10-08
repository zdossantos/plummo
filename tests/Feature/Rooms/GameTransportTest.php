<?php

use App\Events\RoomChanged;
use App\Models\Game;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

it('authorizes private room updates only for the screen or a connected player', function () {
    $room = openRoom();
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-room.'.$room->code];
    $this->postJson('/rooms/'.$room->code.'/broadcast-auth', $payload)->assertOk();
    $this->flushSession();
    $this->postJson('/rooms/'.$room->code.'/broadcast-auth', $payload)->assertForbidden();
    [$player, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/broadcast-auth', $payload)->assertOk();
    $this->postJson('/rooms/'.$room->code.'/broadcast-auth', [...$payload, 'channel_name' => 'private-room.OTHER'])->assertForbidden();
    $this->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/broadcast-auth', $payload)->assertForbidden();
});

it('broadcasts only an invalidation and processes deadlines without a phone request', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 10])->assertCreated();
    $event = new RoomChanged($room->code);
    expect($event->broadcastWith())->toBe([])->and($event->broadcastOn()->name)->toBe('private-room.'.$room->code);
    $this->travel(10)->seconds();
    $this->artisan('games:tick')->assertSuccessful();
    $this->assertDatabaseHas('games', ['room_id' => $room->id, 'status' => 'active']);
    expect(Game::where('room_id', $room->id)->sole()->state['phase'])->toBe('reveal');
});
