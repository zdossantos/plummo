<?php

use App\Models\Game;
use App\Models\RoomPlayer;
use App\Services\GameEngine;
use App\Services\MiniGameScenes;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

function miniSettings(array $trials = ['memory']): array
{
    return ['type' => 'mini_games', 'trials' => $trials, 'rounds' => 5, 'duration' => 20];
}

it('starts without packs and exposes only one Mini games entry and eligible trials', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'mini_games'])->assertOk()->assertJsonPath('packs', [])->assertJsonCount(4, 'trials');
    $this->postJson('/rooms/'.$room->code.'/games', miniSettings())->assertCreated()->assertJsonPath('game.type', 'mini_games')->assertJsonPath('game.phase', 'observing')->assertJsonPath('game.round.scene', null)->assertJsonPath('game.round.correct', null)->assertJsonPath('game.round.choices', []);
    $this->assertDatabaseCount('room_content_history', 0);
});

it('rejects unsupported duplicate empty and unavailable trials and bad settings', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    foreach ([[], ['invalid'], ['memory', 'memory'], ['author']] as $trials) {
        $this->postJson('/rooms/'.$room->code.'/games', miniSettings($trials))->assertUnprocessable();
    }
    $this->postJson('/rooms/'.$room->code.'/games', [...miniSettings(), 'duration' => 61])->assertUnprocessable();
    $this->postJson('/rooms/'.$room->code.'/games', [...miniSettings(), 'rounds' => 4])->assertUnprocessable();
});

it('freezes the observation during pause and resumes at the same scene progress', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->postJson('/rooms/'.$room->code.'/games', miniSettings(['cups']))->assertCreated();
    $this->travel(2)->seconds();
    $before = app(GameEngine::class)->view($room->refresh(), null, true)['round']['elapsed'];
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->travel(8)->seconds();
    $frozen = app(GameEngine::class)->view($room->refresh(), null, true);
    expect($frozen['round']['elapsed'])->toEqualWithDelta($before, 0.05);
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertOk();
    $this->travel(5)->seconds();
    $view = $this->postJson('/rooms/'.$room->code.'/presence')->assertOk()->assertJsonPath('game.phase', 'observing');
    expect($view->json('game.round.elapsed'))->toEqualWithDelta($before, 0.05);
});

it('rejects observation answers and awards once with a private solution until reveal', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings())->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertConflict();
    $this->travel(8)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'answer')->assertJsonPath('game.round.scene', null)->assertJsonPath('game.round.correct', null);
    $game = Game::latest('id')->firstOrFail();
    $correct = $game->state['round']['payload']['correct'];
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $correct))->assertOk()->assertJsonPath('game.round.answers', []);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $correct))->assertConflict();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $correct))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(100)->and(RoomPlayer::findOrFail($second)->score)->toBe(30);
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect(RoomPlayer::findOrFail($first)->score)->toBe(100);
});

it('rejects choices outside the actual trial and late round identifiers', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->postJson('/rooms/'.$room->code.'/games', miniSettings(['hide']))->assertCreated()->assertJsonCount(12, 'game.round.choices');
    $this->postJson('/rooms/'.$room->code.'/answer', [...quizAnswer($room, 0), 'round' => 999])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 12))->assertUnprocessable();
    $game = Game::latest('id')->firstOrFail();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $game->state['round']['payload']['correct']))->assertOk();
});

it('writes privately and excludes the selected author from guessing and scoring', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second, $two] = enterRoom($room, 'Autre');
    [$third, $three] = enterRoom($room, 'Encore');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['author']))->assertCreated()->assertJsonPath('game.phase', 'writing');
    $game = Game::latest('id')->firstOrFail();
    $data = ['game_id' => $game->id, 'round' => 1, 'text' => 'Mon secret rigolo', 'submit' => true];
    $this->postJson('/rooms/'.$room->code.'/mini-write', $data)->assertOk()->assertJsonPath('game.me.submitted', true);
    $this->postJson('/rooms/'.$room->code.'/mini-write', $data)->assertConflict();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/mini-write', [...$data, 'text' => ''])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.text', null)->assertJsonPath('game.me.draft', '');
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/mini-write', [...$data, 'text' => ''])->assertJsonPath('game.phase', 'answer')->assertJsonPath('game.round.text', 'Mon secret rigolo')->assertJsonPath('game.round.correct', null);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(0)->and(RoomPlayer::findOrFail($second)->score)->toBe(100)->and(RoomPlayer::findOrFail($third)->score)->toBe(30);
});

it('uses every selected trial per cycle and finishes at the configured count', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token);
    $this->postJson('/rooms/'.$room->code.'/games', miniSettings(['memory', 'hide', 'count', 'cups']))->assertCreated();
    $played = [];
    foreach (range(1, 5) as $number) {
        $game = Game::latest('id')->firstOrFail();
        $played[] = $game->state['trial'];
        $state = $game->state;
        $state['phase'] = 'reveal';
        $state['deadline'] = app(GameEngine::class)->time();
        $game->update(['state' => $state]);
        $this->getJson('/rooms/'.$room->code.'/state')->assertOk()->assertJsonPath('game.phase', $number === 5 ? 'results' : Game::latest('id')->firstOrFail()->state['phase']);
    }
    expect(array_unique(array_slice($played, 0, 4)))->toHaveCount(4)->and($played[3])->not->toBe($played[4]);
});

it('keeps bonuses unusable during observation and starts their clock only with answers', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', [...miniSettings(), 'bonuses' => true])->assertCreated()->assertJsonPath('game.bonuses.canUse', false);
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['bonuses']['debt'] = 6;
    $game->update(['state' => $state]);
    $this->travel(8)->seconds();
    $response = $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.canUse', true);
    expect($response->json('game.deadline') - $response->json('serverTime'))->toEqualWithDelta(14, 0.05);
});

it('keeps late arrivals out until the next challenge and continues after a departure', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['hide']))->assertCreated();
    [$late, $token] = enterRoom($room, 'Arrivant');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
    RoomPlayer::findOrFail($second)->update(['left_at' => now()]);
    $game = Game::latest('id')->firstOrFail();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, $game->state['round']['payload']['correct']))->assertJsonPath('game.phase', 'reveal');
    $this->travel(4)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2)->assertJsonPath('game.me.eligible', true);
});

it('waits when only the author challenge is selected and resumes once three players return', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second] = enterRoom($room, 'Autre');
    [$third] = enterRoom($room, 'Encore');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['author']))->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['phase'] = 'reveal';
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);
    RoomPlayer::findOrFail($third)->update(['disconnected_at' => now()]);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'waiting')->assertJsonPath('game.round.number', 1);
    RoomPlayer::findOrFail($third)->update(['disconnected_at' => null, 'last_seen_at' => now()]);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'writing')->assertJsonPath('game.round.number', 2);
});

it('reveals an empty writing round without inventing answers or points', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    enterRoom($room, 'Autre');
    enterRoom($room, 'Encore');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['author']))->assertCreated();
    $game = Game::latest('id')->firstOrFail();
    $state = $game->state;
    $state['deadline'] = app(GameEngine::class)->time();
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.correct', null)->assertJsonPath('game.round.choices', [])->assertJsonPath('game.me.points', 0);
    expect(RoomPlayer::findOrFail($first)->score)->toBe(0);
});

it('generates count and cup scenes whose visible events agree with the solution', function () {
    $scenes = app(MiniGameScenes::class);
    foreach (range(1, 100) as $iteration) {
        $count = $scenes->generate('count');
        $residents = [];
        foreach ($count['scene']['events'] as $event) {
            if ($event['direction'] === 1) {
                $residents[] = $event['color'];
            } else {
                expect(array_pop($residents))->toBe($event['color']);
            }
        }
        expect(count($residents))->toBe($count['correct'])->and($residents)->toBe($count['scene']['residents']);
        $cups = $scenes->generate('cups');
        $order = [0, 1, 2];
        foreach ($cups['scene']['swaps'] as $swap) {
            [$a, $b] = $swap['positions'];
            [$order[$a], $order[$b]] = [$order[$b], $order[$a]];
            expect($swap['duration'])->toBe(0.7);
        }
        expect($order[$cups['correct']])->toBe(0);
    }
});

it('readmits a withdrawn player in the following challenge', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['hide']))->assertCreated();
    app(GameEngine::class)->withdraw($room, RoomPlayer::findOrFail($second));
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertForbidden();
    $game = Game::latest('id')->firstOrFail();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    $this->travel(4)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', true);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
});

it('numbers homonymous authors so their answers stay distinguishable', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room, 'Alex');
    [$second, $two] = enterRoom($room, 'Alex');
    [$third, $three] = enterRoom($room, 'Alex');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['author']))->assertCreated()->assertJsonPath('game.me.authorNumber', 1);
    $game = Game::latest('id')->firstOrFail();
    $data = ['game_id' => $game->id, 'round' => 1, 'text' => 'Secret', 'submit' => true];
    $this->postJson('/rooms/'.$room->code.'/mini-write', $data)->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/mini-write', [...$data, 'text' => ''])->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $three)->postJson('/rooms/'.$room->code.'/mini-write', [...$data, 'text' => ''])->assertJsonPath('game.round.choices', ['1 · Alex', '2 · Alex', '3 · Alex'])->assertJsonPath('game.me.authorNumber', 3);
});

it('keeps author participants as a list after a player in the middle leaves', function () {
    $room = openRoom();
    [$first, $one] = enterRoom($room);
    [$second] = enterRoom($room, 'Partant');
    [$third] = enterRoom($room, 'Alex');
    [$fourth] = enterRoom($room, 'Morgan');
    RoomPlayer::findOrFail($second)->update(['left_at' => now()]);
    $response = $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', miniSettings(['author']))->assertCreated();
    expect($response->json('game.round.participants'))->toBe([$first, $third, $fourth]);
});
