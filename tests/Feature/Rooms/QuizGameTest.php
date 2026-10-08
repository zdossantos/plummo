<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\RoomPlayer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

it('allows only the connected chief to launch a validated quiz and hides its answer', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    enterRoom($room, 'Autre');
    $pack = quizPack();
    $settings = ['type' => 'quiz', 'packs' => [$pack], 'rounds' => 5, 'duration' => 30, 'allow_repeats' => false];
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', [...$settings, 'rounds' => 4])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated()->assertJsonPath('game.phase', 'answer')->assertJsonMissingPath('game.round.payload.correct')->assertJsonCount(4, 'game.round.choices');
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertConflict();
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'configure', 'target' => null])->assertConflict();
});

it('accepts one definitive answer per round and commits rapidity points once when all answer', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk()->assertJsonPath('game.me.answered', true);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertConflict();
    expect(RoomPlayer::findOrFail($first)->score)->toBe(0);
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk()->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(100)->and(RoomPlayer::findOrFail($second)->score)->toBe(30);
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect(RoomPlayer::findOrFail($first)->score)->toBe(100);
    $this->travel(3)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'answer')->assertJsonPath('game.round.number', 2);
});

it('freezes actions and countdown during pause and resumes after five seconds', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk()->assertJsonPath('game.phase', 'paused');
    $this->travel(10)->seconds();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertOk()->assertJsonPath('game.phase', 'resuming');
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertConflict();
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk()->assertJsonPath('game.phase', 'answer');
});

it('shares same-instant correct ranks and stops at the configured question count', function () {
    $this->freezeTime();
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $pack = quizPack();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [$pack], 'rounds' => 5, 'duration' => 10])->assertCreated();
    foreach (range(1, 5) as $number) {
        $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
        $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
        $this->travel(3)->seconds();
        $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
        $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', $number === 5 ? 'results' : 'answer');
    }
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(325)->and(RoomPlayer::findOrFail($other)->score)->toBe(325);
    $this->postJson('/rooms/'.$room->code.'/game/lobby')->assertOk()->assertJsonPath('game', null);
    $this->assertDatabaseCount('room_content_history', 5);
});

it('expires a question without a reply and rejects late answers', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 10])->assertCreated();
    $this->travel(10)->seconds();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(0);
});

it('requires a pause before stopping and cancels only current round gains', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    [$other] = enterRoom($room, 'Autre');
    RoomPlayer::findOrFail($chief)->update(['score' => 200]);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/stop')->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/stop')->assertOk()->assertJsonPath('game', null);
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(200);
    $this->assertDatabaseCount('room_content_history', 1);
});

it('finishes the current round at the point target and preserves its results', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $room->update(['point_target' => 50]);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'results');
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'results')->assertJsonPath('room.pointTarget', 50);
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(65);
    $this->assertDatabaseCount('room_content_history', 1);
});

it('waits newcomers until the next round and excludes wrong answers from rapidity ranks', function () {
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    [$late, $three] = enterRoom($room, 'Nouveau');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($other)->score)->toBe(100)->and(RoomPlayer::findOrFail($chief)->score)->toBe(0)->and(RoomPlayer::findOrFail($late)->score)->toBe(0);
    $this->travel(3)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', true);
});

it('pauses instead of completing a round when all players disconnect and resumes with a countdown', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->travel(16)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(0);
});

it('pauses when the screen is missing and never lets its return cancel a manual pause', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->travel(10)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $this->travel(6)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertJsonPath('game.phase', 'resuming');
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
});

it('keeps a reconnected player waiting until the next question without changing the original scoring population', function () {
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->travel(10)->seconds();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $this->travel(6)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.me.eligible', false);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(100);
    $this->travel(3)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', true);
});

it('protects screen heartbeats with the separate screen session', function () {
    $room = openRoom();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->flushSession();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertForbidden();
});

it('does not let an immediate departure and return rejoin the current question', function () {
    $this->freezeTime();
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.me.eligible', false);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
});

it('pauses on exhausted content and lets the chief explicitly allow repeats without losing scores', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $pack = quizPack();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [$pack], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $revealed = Game::where('room_id', $room->id)->sole()->state['round']['content_id'];
    Content::where('id', '!=', $revealed)->update(['published' => false]);
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'paused')->assertJsonPath('game.exhausted', true);
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => [$pack], 'allow_repeats' => true])->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(65);
});

it('requires a pause before the chief closes a running mini-game', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->deleteJson('/rooms/'.$room->code, ['confirm' => true])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->deleteJson('/rooms/'.$room->code, ['confirm' => true])->assertNoContent();
});

it('requires extending the reached target before another mini-game can start', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $room->update(['point_target' => 50]);
    RoomPlayer::findOrFail($chief)->update(['score' => 65]);
    $settings = ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30];
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable()->assertJsonValidationErrors('target');
    $this->patchJson('/rooms/'.$room->code.'/session', ['action' => 'extend', 'extra' => 100])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated();
});

it('distinguishes the global winner from the current mini-game winner at the target', function () {
    $room = openRoom();
    [$chief, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $room->update(['point_target' => 100]);
    RoomPlayer::findOrFail($other)->update(['score' => 90]);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->travel(3)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'results')->assertJsonPath('game.targetReached', true)->assertJsonPath('room.ranking.0.id', $other);
});

it('rejects a delayed answer from an earlier round or game', function () {
    $this->freezeTime();
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 10])->assertCreated();
    $oldAnswer = quizAnswer($room, 0);
    $this->travel(10)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'reveal');
    $this->travel(4)->seconds();
    $this->postJson('/rooms/'.$room->code.'/answer', $oldAnswer)->assertConflict();
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(0);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.me.answered', false);
    $current = quizAnswer($room, 0);
    $this->postJson('/rooms/'.$room->code.'/answer', [...$current, 'game_id' => $current['game_id'] + 1])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/answer', $current)->assertOk();
});
