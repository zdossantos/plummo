<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\RoomPlayer;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);
beforeEach(function () {
    $this->withCredentials();
    $this->freezeTime();
    Storage::fake('local');
    Storage::disk('local')->put('audio/test.wav', 'prepared audio');
});

it('plays eight immediate choices with the same scoring and remembers only the correct song', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second, $two] = enterRoom($room, 'Alex');
    $settings = ['type' => 'blind_test', 'packs' => [blindPack()], 'rounds' => 5, 'duration' => 30];
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', [...$settings, 'duration' => 9])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated()->assertJsonCount(8, 'game.round.choices')->assertJsonPath('game.round.correct', null)->assertJsonPath('game.round.audio', null)->assertJsonMissingPath('game.round.audio_path');
    $game = Game::where('room_id', $room->id)->sole();
    $choice = $game->state['round']['payload']['correct'];
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $choice))->assertOk();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $choice))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(100)->and(RoomPlayer::findOrFail($second)->score)->toBe(30);
    $this->assertDatabaseCount('room_content_history', 1);
    $firstContent = $game->state['round']['content_id'];
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    $this->assertDatabaseCount('room_content_history', 2);
    expect($game->fresh()->state['round']['content_id'])->not->toBe($firstContent);
});

it('serves copied audio only to the owning screen and rejects stale rounds', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'blind_test', 'packs' => [blindPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $response = $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $url = $response->json('game.round.audio');
    expect($url)->toBeString();
    $game = Game::where('room_id', $room->id)->sole();
    $source = Content::findOrFail($game->state['round']['content_id']);
    Storage::disk('local')->delete($source->payload['audio_path']);
    $source->delete();
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->withSession(['plummo.screen' => null])->get($url)->assertForbidden();
    $this->withSession(['plummo.screen' => $room->id])->get($url)->assertOk();
    Storage::disk('local')->put('audio/test.wav', 'next audio');
    blindSong('Replacement', [Tag::where('name', 'Music')->sole()->id]);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $game->state['round']['payload']['correct']))->assertOk();
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    $this->get($url)->assertNotFound();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->deleteJson('/rooms/'.$room->code, ['confirm' => true])->assertNoContent();
    Storage::disk('local')->assertDirectoryEmpty('games/'.$room->id);
});

it('refuses an insufficient global catalogue and an unavailable audio without recording a reveal', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $pack = blindPack();
    $last = Content::latest('id')->firstOrFail();
    $last->update(['published' => false]);
    $settings = ['type' => 'blind_test', 'packs' => [$pack], 'rounds' => 5, 'duration' => 30];
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable()->assertJsonValidationErrors('packs');
    $last->update(['published' => true]);
    Storage::disk('local')->delete('audio/test.wav');
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    $this->assertDatabaseCount('games', 0);
    $this->assertDatabaseCount('room_content_history', 0);
});

it('keeps blind test pause and recovery settings attached to its own catalogue', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $pack = blindPack();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'blind_test', 'packs' => [$pack], 'rounds' => 5, 'duration' => 10])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'answer');
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 7))->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $game = Game::where('room_id', $room->id)->sole();
    $state = $game->state;
    $state['exhausted'] = true;
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => [$pack], 'allow_repeats' => false])->assertJsonPath('game.type', 'blind_test')->assertJsonPath('game.phase', 'resuming');
});

it('does not consume a song when its audio disappears before the next round', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'blind_test', 'packs' => [blindPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $game->state['round']['payload']['correct']))->assertOk();
    Storage::disk('local')->delete('audio/test.wav');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'paused')->assertJsonPath('game.exhausted', true);
    $this->assertDatabaseCount('room_content_history', 1);
});
