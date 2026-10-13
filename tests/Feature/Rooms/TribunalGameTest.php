<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\RoomPlayer;
use App\Services\BonusClock;
use App\Services\GameEngine;
use Database\Seeders\TribunalCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(function () {
    $this->withCredentials();
    $this->seed(TribunalCatalogSeeder::class);
});

function tribunalSettings(array $extra = []): array
{
    return [...['type' => 'tribunal', 'rounds' => 1, 'duration' => 20, 'packs' => [Pack::where('name', 'Tribunal · Français')->sole()->id]], ...$extra];
}

function tribunalDeadline(): Game
{
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);

    return $game;
}

it('draws a new published subject from the selected pack without repetition', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings())->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $previous = $state['round']['content_id'];
    $state['phase'] = 'reveal';
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);
    $view = $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->assertJsonPath('game.phase', 'preparing')->json('game');
    expect($view['round']['question'])->toBeIn(array_values(__('tribunal.topics', [], 'fr')));
    expect($game->fresh()->state['round']['content_id'])->not->toBe($previous);
});

it('requires three players and starts with packs with two advocates and a jury', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->startReadyGame($room, tribunalSettings())->assertUnprocessable();
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'tribunal'])->assertOk()->assertJsonCount(2, 'packs');
    $response = $this->startReadyGame($room, tribunalSettings())->assertCreated()->assertJsonPath('game.phase', 'preparing')->assertJsonCount(2, 'game.round.advocates');
    expect($response->json('game.round.question'))->not->toBeEmpty();
    $this->assertDatabaseCount('room_content_history', 1);
});

it('permits only the current speaker to finish and keeps votes private until a single award', function () {
    $room = openRoom();
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois', 'Quatre'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->startReadyGame($room, tribunalSettings())->assertCreated();
    tribunalDeadline();
    $view = $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'speaking_first')->json('game');
    $data = ['game_id' => $view['id'], 'round' => 1];
    $speaker = $view['round']['speakerId'];
    $advocates = $view['round']['advocates'];
    $other = $advocates[0] === $speaker ? $advocates[1] : $advocates[0];
    $jury = array_values(array_diff(array_keys($tokens), $advocates));
    $this->withCookie('plummo_player_'.$room->code, $tokens[$other])->postJson('/rooms/'.$room->code.'/tribunal/finish', $data)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $tokens[$speaker])->postJson('/rooms/'.$room->code.'/tribunal/finish', $data)->assertJsonPath('game.phase', 'speaking_second');
    $this->postJson('/rooms/'.$room->code.'/tribunal/finish', $data)->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $tokens[$other])->postJson('/rooms/'.$room->code.'/tribunal/finish', $data)->assertJsonPath('game.phase', 'voting');
    $this->postJson('/rooms/'.$room->code.'/tribunal/vote', [...$data, 'choice' => 0])->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury[0]])->postJson('/rooms/'.$room->code.'/tribunal/vote', [...$data, 'choice' => 0])->assertOk()->assertJsonPath('game.round.votes', [])->assertJsonPath('game.me.choice', 0);
    $this->postJson('/rooms/'.$room->code.'/tribunal/vote', [...$data, 'choice' => 1])->assertConflict();
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury[1]])->postJson('/rooms/'.$room->code.'/tribunal/vote', [...$data, 'choice' => 0])->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.counts', [2, 0]);
    expect(RoomPlayer::findOrFail($advocates[0])->score)->toBe(130)->and(RoomPlayer::findOrFail($other)->score)->toBe($other === $advocates[0] ? 130 : 0);
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect(RoomPlayer::findOrFail($advocates[0])->score)->toBe(130);
});

it('gives everyone each camp once per tour without repeating a topic', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings())->assertCreated();
    $camps = [[], []];
    $topics = [];
    foreach (range(1, 3) as $round) {
        $view = $this->getJson('/rooms/'.$room->code.'/state')->json('game');
        $topics[] = $view['round']['question'];
        foreach ([0, 1] as $camp) {
            $camps[$camp][] = $view['round']['advocates'][$camp];
        }
        foreach (range(1, 4) as $phase) {
            tribunalDeadline();
            $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
        }
        tribunalDeadline();
        $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', $round === 3 ? 'results' : 'preparing');
    }
    expect(array_unique($camps[0]))->toHaveCount(3)->and(array_unique($camps[1]))->toHaveCount(3)->and(array_unique($topics))->toHaveCount(3);
    expect(Game::latest('id')->firstOrFail()->status)->toBe('finished');
});

it('cancels a debate when its advocate leaves and never pays its partial votes', function () {
    $room = openRoom();
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois', 'Quatre'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->startReadyGame($room, tribunalSettings())->assertCreated();
    foreach (range(1, 3) as $phase) {
        tribunalDeadline();
        $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    }
    $game = Game::latest('id')->firstOrFail();
    $advocates = $game->state['round']['advocates'];
    $jury = array_values(array_diff(array_keys($tokens), $advocates));
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury[0]])->postJson('/rooms/'.$room->code.'/tribunal/vote', ['game_id' => $game->id, 'round' => 1, 'choice' => 0])->assertOk();
    RoomPlayer::findOrFail($advocates[0])->update(['left_at' => now()]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.canceled', true)->assertJsonPath('game.round.votes', []);
    expect($room->players()->sum('score'))->toBe(0);
    RoomPlayer::findOrFail($advocates[0])->update(['left_at' => null]);
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.round.canceled', false);
});

it('retains a departed juror vote and finishes when remaining connected jurors have voted', function () {
    $room = openRoom();
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois', 'Quatre', 'Cinq'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->startReadyGame($room, tribunalSettings())->assertCreated();
    foreach (range(1, 3) as $phase) {
        tribunalDeadline();
        $this->getJson('/rooms/'.$room->code.'/state');
    }
    $game = Game::latest('id')->firstOrFail();
    $advocates = $game->state['round']['advocates'];
    $jury = array_values(array_diff(array_keys($tokens), $advocates));
    $data = ['game_id' => $game->id, 'round' => 1, 'choice' => 0];
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury[0]])->postJson('/rooms/'.$room->code.'/tribunal/vote', $data)->assertOk();
    RoomPlayer::findOrFail($jury[0])->update(['left_at' => now()]);
    RoomPlayer::findOrFail($jury[2])->update(['left_at' => now()]);
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury[1]])->postJson('/rooms/'.$room->code.'/tribunal/vote', [...$data, 'choice' => 1])->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.counts', [1, 1]);
    expect(RoomPlayer::findOrFail($advocates[0])->score)->toBe(65)->and(RoomPlayer::findOrFail($advocates[1])->score)->toBe(65);
});

it('keeps newcomers out of the current tour and includes them in the next one', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings(['rounds' => 2]))->assertCreated();
    [$late, $lateToken] = enterRoom($room, 'Nouveau');
    $this->withCookie('plummo_player_'.$room->code, $lateToken);
    foreach (range(1, 3) as $round) {
        $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', false);
        foreach (range(1, 5) as $phase) {
            tribunalDeadline();
            $this->getJson('/rooms/'.$room->code.'/state');
        }
    }
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.tour', 2)->assertJsonPath('game.me.eligible', true);
});

it('applies bonus clock debt to voting only even with a long speech duration', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings(['duration' => 60, 'bonuses' => true]))->assertCreated()->assertJsonPath('game.bonuses.canUse', false);
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['bonuses']['debt'] = 6;
    $game->update(['state' => $state]);
    foreach (range(1, 2) as $phase) {
        tribunalDeadline();
        $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.canUse', false);
        expect($response->json('game.deadline') - $response->json('serverTime'))->toEqualWithDelta(60, 0.1);
    }
    tribunalDeadline();
    $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.canUse', true);
    expect($response->json('game.deadline') - $response->json('serverTime'))->toEqualWithDelta(14, 0.1);
    expect(app(BonusClock::class)->step($game))->toBe(6);
});

it('moves on to a new tour when no original jury remains but newcomers can play', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    [$two] = enterRoom($room, 'Deux');
    [$three] = enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings(['rounds' => 2]))->assertCreated();
    enterRoom($room, 'Nouveau');
    enterRoom($room, 'Autre');
    RoomPlayer::findOrFail($two)->update(['left_at' => now()]);
    RoomPlayer::findOrFail($three)->update(['left_at' => now()]);
    $this->withCookie('plummo_player_'.$room->code, $token)->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.canceled', true);
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'preparing')->assertJsonPath('game.round.tour', 2);
});

it('skips the rest of a tour with two original survivors instead of waiting forever for its jury', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings(['rounds' => 2]))->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $jury = array_values(array_diff($game->state['round']['participants'], $game->state['round']['advocates']))[0];
    $state = $game->state;
    $state['queue'] = [$state['round']['advocates']];
    $game->update(['state' => $state]);
    enterRoom($room, 'Nouveau');
    RoomPlayer::findOrFail($jury)->update(['left_at' => now()]);
    foreach (range(1, 5) as $phase) {
        tribunalDeadline();
        $this->getJson('/rooms/'.$room->code.'/state');
    }
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'preparing')->assertJsonPath('game.round.tour', 2);
});

it('freezes a speech through pause and rejects stale or malformed actions', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings())->assertCreated();
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'speaking_first');
    $game = Game::latest('id')->firstOrFail();
    $remaining = $game->state['deadline'] - app(GameEngine::class)->time();
    $this->postJson('/rooms/'.$room->code.'/tribunal/vote', ['game_id' => $game->id, 'round' => 1, 'choice' => 2])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/tribunal/finish', ['game_id' => $game->id, 'round' => 2])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/tribunal/finish', ['game_id' => $game->id, 'round' => 1])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    tribunalDeadline();
    $response = $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'speaking_first');
    expect($response->json('game.deadline') - $response->json('serverTime'))->toEqualWithDelta($remaining, .5);
});

it('stops after the verdict when the session target is reached', function () {
    $room = openRoom();
    $room->update(['point_target' => 50]);
    $tokens = [];
    foreach (['Un', 'Deux', 'Trois'] as $name) {
        [$id, $token] = enterRoom($room, $name);
        $tokens[$id] = $token;
    }
    $this->withCookie('plummo_player_'.$room->code, reset($tokens))->startReadyGame($room, tribunalSettings())->assertCreated();
    foreach (range(1, 3) as $phase) {
        tribunalDeadline();
        $this->getJson('/rooms/'.$room->code.'/state');
    }
    $game = Game::latest('id')->firstOrFail();
    $jury = array_values(array_diff(array_keys($tokens), $game->state['round']['advocates']))[0];
    $this->withCookie('plummo_player_'.$room->code, $tokens[$jury])->postJson('/rooms/'.$room->code.'/tribunal/vote', ['game_id' => $game->id, 'round' => 1, 'choice' => 1])->assertJsonPath('game.phase', 'reveal');
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'results')->assertJsonPath('game.targetReached', true);
    expect($room->players()->sum('score'))->toBe('65');
});

it('waits with two players and continues without replaying the canceled debate after return', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings())->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $left = $game->state['round']['advocates'][1];
    RoomPlayer::findOrFail($left)->update(['left_at' => now()]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.canceled', true);
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'waiting');
    RoomPlayer::findOrFail($left)->update(['left_at' => null]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'preparing')->assertJsonPath('game.round.number', 2);
});

it('filters published subjects by pack and snapshots the current subject despite admin edits', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $pack = tribunalSettings()['packs'][0];
    $content = Content::where('type', 'tribunal')->whereHas('tags', fn ($query) => $query->where('name', 'Français'))->firstOrFail();
    Content::where('type', 'tribunal')->where('id', '!=', $content->id)->update(['published' => false]);
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'tribunal', 'packs' => [$pack]])->assertJsonPath('availability.total', 1)->assertJsonCount(1, 'packs');
    $this->startReadyGame($room, tribunalSettings())->assertUnprocessable()->assertJsonValidationErrors('packs');
    $this->startReadyGame($room, [...tribunalSettings(), 'packs' => []])->assertUnprocessable();
    $question = $content->payload['question'];
    $this->startReadyGame($room, tribunalSettings(['allow_repeats' => true]))->assertCreated()->assertJsonPath('game.round.question', $question);
    $content->delete();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.question', $question);
});

it('pauses on exhausted subjects and recovers without skipping a debate or losing scores', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    enterRoom($room, 'Deux');
    enterRoom($room, 'Trois');
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, tribunalSettings())->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $queue = $state['queue'];
    $state['phase'] = 'reveal';
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);
    Content::where('type', 'tribunal')->update(['published' => false]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'paused')->assertJsonPath('game.exhausted', true);
    expect($game->fresh()->state['queue'])->toBe($queue);
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => tribunalSettings()['packs'], 'allow_repeats' => false])->assertUnprocessable();
    $content = Content::findOrFail($state['round']['content_id']);
    $content->update(['published' => true]);
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => tribunalSettings()['packs'], 'allow_repeats' => true])->assertOk()->assertJsonPath('game.phase', 'resuming');
    tribunalDeadline();
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'preparing')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.round.advocates', $queue[0]);
    expect($game->fresh()->state['scores'])->toBe($state['scores']);
});
