<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(function () {
    $this->withCredentials();
    $this->freezeTime();
});

function drawingAction(Room $room, array $values = []): array
{
    $game = Game::findOrFail(Game::where('room_id', $room->id)->orderByDesc('id')->value('id'));

    return ['game_id' => $game->id, 'round' => $game->state['number'], ...$values];
}
function startDrawing(Room $room, string $token, int $tours = 1): Game
{
    test()->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => [drawingPack()], 'rounds' => $tours, 'duration' => 90])->assertCreated();

    return Game::where('room_id', $room->id)->sole();
}
function chooseDrawing(Room $room, string $token): string
{
    test()->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/drawing/choose', drawingAction($room, ['choice' => 0]))->assertOk()->assertJsonPath('game.phase', 'drawing');

    return Game::findOrFail(Game::where('room_id', $room->id)->orderByDesc('id')->value('id'))->state['round']['word'];
}
function drawingHeartbeat(Room $room): void
{
    $room->update(['screen_seen_at' => now()]);
    $room->players()->whereNull('left_at')->whereNull('disconnected_at')->update(['last_seen_at' => now()]);
}

it('requires two players and validates drawing settings, private words and history', function () {
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    $pack = drawingPack();
    $settings = ['type' => 'drawing', 'packs' => [$pack], 'rounds' => 1, 'duration' => 90];
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    [, $two] = enterRoom($room, 'Alex');
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', [...$settings, 'rounds' => 6])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', [...$settings, 'duration' => 29])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated()->assertJsonPath('game.phase', 'selecting')->assertJsonCount(3, 'game.me.words')->assertJsonPath('game.round.artistId', $artist);
    $this->assertDatabaseCount('room_content_history', 3);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonMissingPath('game.me.words')->assertJsonPath('game.round.word', null);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.words', [])->assertJsonPath('game.round.word', null);
    $this->postJson('/rooms/'.$room->code.'/drawing/choose', drawingAction($room, ['choice' => 0]))->assertForbidden();
    $this->travel(15)->seconds();
    drawingHeartbeat($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'drawing');
    expect(Game::where('room_id', $room->id)->sole()->state['deadline'])->toBe(app(GameEngine::class)->time() + 90);
});

it('accepts only exact case-insensitive words, gives a private hint and scores once', function () {
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    [$first, $two] = enterRoom($room, 'Alex');
    [$last, $three] = enterRoom($room, 'Sam');
    startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    $this->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => ' '.$word]))->assertOk()->assertJsonPath('game.me.found', false);
    $this->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => str_replace('É', 'E', $word)]))->assertOk()->assertJsonPath('game.me.near', true)->assertJsonPath('game.me.found', false);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonMissingPath('game.me.near')->assertJsonPath('game.round.word', null);
    $this->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => mb_strtoupper($word)]))->assertOk()->assertJsonPath('game.me.points', 100);
    $this->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertConflict();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.word', $word);
    expect(RoomPlayer::findOrFail($artist)->score)->toBe(65)->and(RoomPlayer::findOrFail($first)->score)->toBe(100)->and(RoomPlayer::findOrFail($last)->score)->toBe(30);
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'selecting')->assertJsonPath('game.round.artistId', $first);
});

it('shares occupied-rank points on ties and preserves acquired points on paused stop', function () {
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    [$first, $two] = enterRoom($room, 'Alex');
    [$last, $three] = enterRoom($room, 'Sam');
    enterRoom($room, 'Pat');
    startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.me.points', 83);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/game/stop')->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertConflict();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/game/stop')->assertOk();
    expect(RoomPlayer::findOrFail($artist)->score)->toBe(43)->and(RoomPlayer::findOrFail($first)->score)->toBe(83)->and(RoomPlayer::findOrFail($last)->score)->toBe(83);
});

it('finishes each player rotation and then returns cumulative results at the point target', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    $game = startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.phase', 'reveal');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'selecting');
    $word = chooseDrawing($room, $two);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.phase', 'reveal');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'results');
    expect($game->fresh()->state['number'])->toBe(2);
    $this->postJson('/rooms/'.$room->code.'/game/lobby')->assertJsonPath('game', null);
    $this->assertDatabaseCount('room_content_history', 6);
    $room->update(['point_target' => 131]);
    $this->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => $game->settings['packs'], 'rounds' => 5, 'duration' => 90])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => $game->settings['packs'], 'rounds' => 1, 'duration' => 90])->assertCreated();
    $word = chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.phase', 'reveal');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'results')->assertJsonPath('game.targetReached', true);
});

it('freezes an absent artist, allows guesses and requires unanimous connected votes to skip', function () {
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    [$first, $two] = enterRoom($room, 'Alex');
    [, $three] = enterRoom($room, 'Sam');
    $game = startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/leave')->assertJsonPath('game.phase', 'artist_missing');
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertJsonPath('game.phase', 'artist_missing');
    $this->postJson('/rooms/'.$room->code.'/drawing/skip', drawingAction($room))->assertJsonPath('game.phase', 'artist_missing');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/skip', drawingAction($room))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($artist)->score)->toBe(33)->and(RoomPlayer::findOrFail($first)->score)->toBe(100);
    expect($game->fresh()->state['round']['canvas'])->toBe([]);
});

it('cancels skip votes and resumes five seconds after artist return with the frozen duration', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    [, $three] = enterRoom($room, 'Sam');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/skip', drawingAction($room))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    drawingHeartbeat($room);
    $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'drawing');
    expect($response->json('game.deadline'))->toBe(app(GameEngine::class)->time() + 85);
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('game.phase', 'paused');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/skip', drawingAction($room))->assertConflict();
});

it('adds new and returning undrawn players at the end of the tour and rejects stale actions', function () {
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    [$next, $two] = enterRoom($room, 'Alex');
    startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    [$new, $three] = enterRoom($room, 'Sam');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $old = drawingAction($room, ['choice' => 0]);
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.artistId', $next);
    $this->postJson('/rooms/'.$room->code.'/drawing/choose', $old)->assertConflict();
    $word = chooseDrawing($room, $two);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $this->travel(3)->seconds();
    drawingHeartbeat($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.artistId', $new);
});

it('retains the current drawing through a general pause and resumes an available artist only once', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('game.phase', 'paused');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    drawingHeartbeat($room);
    $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'drawing');
    expect($response->json('game.deadline'))->toBe(app(GameEngine::class)->time() + 85);
});

it('waits at a drawing boundary with one player and resumes when a second arrives', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    startDrawing($room, $one, 2);
    chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/leave')->assertJsonPath('game.phase', 'reveal');
    $this->travel(3)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'waiting');
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    drawingHeartbeat($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'selecting');
});

it('recovers depleted words without erasing previously revealed history', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    $game = startDrawing($room, $one);
    $word = chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    Content::where('type', 'drawing')->update(['published' => false]);
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.exhausted', true);
    Content::where('type', 'drawing')->update(['published' => true]);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => $game->settings['packs'], 'allow_repeats' => false])->assertJsonPath('game.phase', 'resuming');
    $this->travel(5)->seconds();
    drawingHeartbeat($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'selecting');
    $this->assertDatabaseCount('room_content_history', 6);
});

it('puts a returning undrawn player behind a newer arrival without repeating the artist', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [$later, $two] = enterRoom($room, 'Alex');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    // The initial artist remains marked drawn when a pending player leaves.
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    [$new, $three] = enterRoom($room, 'Sam');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/return')->assertOk();
    $this->travel(3)->seconds();
    drawingHeartbeat($room);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.artistId', $new);
    $word = chooseDrawing($room, $three);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/guess', drawingAction($room, ['guess' => $word]))->assertOk();
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.artistId', $later);
});

function drawingStroke(Room $room, array $values = []): array
{
    return drawingAction($room, ['revision' => 0, 'id' => 1, 'offset' => 0, 'color' => '#35236b', 'width' => 4, 'points' => [[0.1, 0.2], [0.5, 0.8]], ...$values]);
}

it('authorizes drawing commands only for the current artist and keeps words private', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    [, $two] = enterRoom($room, 'Alex');
    startDrawing($room, $one);
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', drawingStroke($room))->assertConflict();
    chooseDrawing($room, $one);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/stroke', drawingStroke($room))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/stroke', drawingStroke($room))->assertOk()->assertJsonCount(1, 'game.round.canvas');
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.word', null)->assertJsonPath('game.me.words', [])->assertJsonPath('game.me.word', null)->assertJsonCount(1, 'game.round.canvas');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/stroke', drawingStroke($room, ['round' => 99]))->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/drawing/clear', drawingAction($room, ['revision' => 0]))->assertConflict();
    expect(Game::where('room_id', $room->id)->sole()->state['round']['canvas'])->toHaveCount(1);
});

it('appends normalized stroke batches idempotently and rejects malformed or excessive input', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    enterRoom($room, 'Alex');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    $url = '/rooms/'.$room->code.'/drawing/stroke';
    $first = drawingStroke($room);
    $this->postJson($url, $first)->assertOk();
    $this->postJson($url, $first)->assertJsonCount(2, 'game.round.canvas.0.points');
    $second = drawingStroke($room, ['offset' => 2, 'points' => [[0.7, 0.9]]]);
    $this->postJson($url, $second)->assertJsonCount(3, 'game.round.canvas.0.points');
    $this->postJson($url, $first)->assertJsonCount(3, 'game.round.canvas.0.points');
    $this->postJson($url, drawingStroke($room, ['points' => [[0.9, 0.9]]]))->assertConflict();
    $this->postJson($url, drawingStroke($room, ['offset' => 4]))->assertConflict();
    $this->postJson($url, drawingStroke($room, ['id' => 3]))->assertConflict();
    foreach ([['points' => [[-0.1, 0.2]]], ['points' => [[1.1, 0]]], ['points' => [[0.5]]], ['points' => []], ['color' => 'url(x)'], ['width' => 13], ['points' => array_fill(0, 201, [0.1, 0.2])]] as $values) {
        $this->postJson($url, drawingStroke($room, $values))->assertUnprocessable();
    }
    $game = Game::where('room_id', $room->id)->sole();
    $state = $game->state;
    $state['round']['canvas'][0]['points'] = array_fill(0, 10000, [0.1, 0.2]);
    $game->update(['state' => $state]);
    $this->postJson($url, drawingStroke($room, ['offset' => 10000, 'points' => [[0.5, 0.5]]]))->assertUnprocessable();
});

it('undoes and clears with revisions that reject delayed drawing updates', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    enterRoom($room, 'Alex');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    $url = '/rooms/'.$room->code.'/drawing/';
    $this->postJson($url.'stroke', drawingStroke($room))->assertOk();
    $this->postJson($url.'stroke', drawingStroke($room, ['id' => 2]))->assertJsonCount(2, 'game.round.canvas');
    $this->postJson($url.'undo', drawingAction($room, ['revision' => 0]))->assertJsonCount(1, 'game.round.canvas')->assertJsonPath('game.round.revision', 1);
    $this->postJson($url.'stroke', drawingStroke($room, ['id' => 2]))->assertConflict();
    $this->postJson($url.'clear', drawingAction($room, ['revision' => 1]))->assertJsonCount(0, 'game.round.canvas')->assertJsonPath('game.round.revision', 2);
    $this->postJson($url.'stroke', drawingStroke($room, ['revision' => 2]))->assertJsonCount(1, 'game.round.canvas');
    $this->postJson($url.'undo', drawingAction($room, ['revision' => 1]))->assertConflict();
});

it('renders completed large drawings without sorting their full JSON payload', function () {
    $room = openRoom();
    [, $one] = enterRoom($room);
    enterRoom($room, 'Alex');
    startDrawing($room, $one);
    chooseDrawing($room, $one);
    $game = Game::where('room_id', $room->id)->sole();
    $state = $game->state;
    $state['round']['canvas'] = [['id' => 1, 'color' => '#35236b', 'width' => 4, 'points' => array_fill(0, 10000, [0.1, 0.2])]];
    $state['phase'] = 'results';
    $game->update(['state' => $state, 'status' => 'finished']);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk()->assertJsonPath('game.phase', 'results');
});
