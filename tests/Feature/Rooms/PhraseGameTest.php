<?php

use App\Models\Content;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Services\GameEngine;
use App\Services\PhraseGame;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);
beforeEach(function () {
    $this->withCredentials();
    $this->freezeTime();
});

function phraseAction(Room $room, array $values = []): array
{
    $game = Game::where('room_id', $room->id)->latest('id')->firstOrFail();

    return ['game_id' => $game->id, 'round' => $game->state['number'], ...$values];
}
function phrasePost(Room $room, string $token, string $action, array $values = [])
{
    return test()->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/phrases/'.$action, phraseAction($room, $values));
}
function phraseSetup(int $rounds = 1): array
{
    $room = openRoom();
    $players = [enterRoom($room), enterRoom($room, 'Alex'), enterRoom($room, 'Sam')];
    test()->withCookie('plummo_player_'.$room->code, $players[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'phrase', 'packs' => [phrasePack()], 'rounds' => $rounds, 'duration' => 60])->assertCreated()->assertJsonPath('game.phase', 'writing');

    return [$room, $players];
}
function phraseAdvance(Room $room, int $seconds): void
{
    test()->travel($seconds)->seconds();
    $room->update(['screen_seen_at' => now()]);
    $room->players()->whereNull('left_at')->whereNull('disconnected_at')->update(['last_seen_at' => now()]);
    app(GameEngine::class)->tick($room);
}
function phraseVoting(Room $room, array $players): array
{
    foreach ($players as $index => [$id, $token]) {
        phrasePost($room, $token, 'submit', ['suffix' => 'texte '.$index])->assertOk();
    }
    foreach ($players as $player) {
        phraseAdvance($room, 5);
    }
    $game = Game::where('room_id', $room->id)->sole();
    expect($game->state['phase'])->toBe('voting');

    return collect($game->state['round']['entries'])->mapWithKeys(fn ($entry) => [$entry['author'] => $entry['id']])->all();
}

it('requires three players, valid settings and chief authorization', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $settings = ['type' => 'phrase', 'packs' => [phrasePack()], 'rounds' => 1, 'duration' => 60];
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    enterRoom($room, 'Alex');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', $settings)->assertUnprocessable();
    enterRoom($room, 'Sam');
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertForbidden();
    foreach ([['rounds' => 0], ['rounds' => 6], ['duration' => 29], ['duration' => 151]] as $invalid) {
        $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/games', [...$settings, ...$invalid])->assertUnprocessable();
    }
    $this->postJson('/rooms/'.$room->code.'/games', $settings)->assertCreated();
    $this->assertDatabaseCount('room_content_history', 1);
});

it('keeps drafts private, locks submitted text and counts Unicode suffix characters', function () {
    // Reproduces a timestamp whose JSON round trip changes the last floating-point bit.
    $this->travelTo(CarbonImmutable::createFromTimestamp('1791464054.078858'));
    [$room, $players] = phraseSetup();
    [, $one] = $players[0];
    [, $two] = $players[1];
    $text = str_repeat('é', 150);
    phrasePost($room, $one, 'draft', ['suffix' => $text])->assertOk()->assertJsonPath('game.me.draft', $text)->assertJsonPath('game.me.submitted', false);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonMissingPath('game.me.draft')->assertJsonPath('game.round.entries', []);
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.draft', '')->assertJsonPath('game.round.entries', []);
    phrasePost($room, $one, 'draft', ['suffix' => $text.'é'])->assertUnprocessable();
    phrasePost($room, $one, 'submit', ['suffix' => $text])->assertOk()->assertJsonPath('game.me.submitted', true);
    phrasePost($room, $one, 'draft', ['suffix' => 'changed'])->assertConflict();
    phrasePost($room, $one, 'submit', ['suffix' => 'changed'])->assertConflict();
    phrasePost($room, $two, 'submit', ['suffix' => ''])->assertOk();
    phrasePost($room, $players[2][1], 'submit', ['suffix' => ''])->assertJsonPath('game.phase', 'presenting');
    expect(Game::where('room_id', $room->id)->sole()->state['deadline'])->toEqualWithDelta(app(GameEngine::class)->time() + 11.05, 0.000001);
});

it('automatically submits saved drafts at expiry and presents anonymously with bounded duration', function () {
    [$room, $players] = phraseSetup();
    phrasePost($room, $players[0][1], 'draft', ['suffix' => str_repeat('z', 150)])->assertOk();
    phraseAdvance($room, 60);
    $response = $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'presenting')->assertJsonCount(1, 'game.round.entries');
    $response->assertJsonMissingPath('game.round.entries.0.author')->assertJsonMissingPath('game.round.entries.0.votes')->assertJsonMissingPath('game.round.entries.0.points');
    phraseAdvance($room, 12);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'voting');
    phraseAdvance($room, 30);
    expect(RoomPlayer::findOrFail($players[0][0])->score)->toBe(0);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'reveal')->assertJsonPath('game.round.entries.0.author', $players[0][0]);
});

it('refuses self votes, duplicates and delayed actions and awards exactly 65 points per vote', function () {
    [$room, $players] = phraseSetup(2);
    $entries = phraseVoting($room, $players);
    [$first, $one] = $players[0];
    [$second, $two] = $players[1];
    [$third, $three] = $players[2];
    phrasePost($room, $one, 'vote', ['choice' => $entries[$first]])->assertForbidden();
    phrasePost($room, $one, 'vote', ['choice' => 'invalid'])->assertUnprocessable();
    phrasePost($room, $one, 'vote', ['choice' => $entries[$second]])->assertOk();
    phrasePost($room, $one, 'vote', ['choice' => $entries[$third]])->assertConflict();
    phrasePost($room, $two, 'vote', ['choice' => $entries[$first]])->assertOk();
    $old = phraseAction($room, ['choice' => $entries[$first]]);
    phrasePost($room, $three, 'vote', ['choice' => $entries[$first]])->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(130)->and(RoomPlayer::findOrFail($second)->score)->toBe(65);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.round.awards.'.$first, 130);
    $this->getJson('/rooms/'.$room->code.'/state');
    expect(RoomPlayer::findOrFail($first)->score)->toBe(130);
    phraseAdvance($room, 3);
    $this->postJson('/rooms/'.$room->code.'/phrases/vote', $old)->assertConflict();
    phrasePost($room, $one, 'draft', ['suffix' => 'next'])->assertOk();
});

it('keeps votes received by abstaining or departed authors and excludes returners until the next tour', function () {
    [$room, $players] = phraseSetup(2);
    $entries = phraseVoting($room, $players);
    [$author, $one] = $players[0];
    phrasePost($room, $players[1][1], 'vote', ['choice' => $entries[$author]])->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('game.me.eligible', false);
    phrasePost($room, $one, 'vote', ['choice' => $entries[$players[1][0]]])->assertForbidden();
    phraseAdvance($room, 30);
    expect(RoomPlayer::findOrFail($author)->score)->toBe(65);
    phraseAdvance($room, 3);
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', true);
});

it('freezes the draft during pause and cancels an unfinished tour while preserving earlier points', function () {
    [$room, $players] = phraseSetup(2);
    $entries = phraseVoting($room, $players);
    phrasePost($room, $players[1][1], 'vote', ['choice' => $entries[$players[0][0]]])->assertOk();
    phraseAdvance($room, 30);
    phraseAdvance($room, 3);
    $one = $players[0][1];
    phrasePost($room, $one, 'draft', ['suffix' => 'saved'])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/stop')->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    phrasePost($room, $one, 'draft', ['suffix' => 'rejected'])->assertConflict();
    phraseAdvance($room, 90);
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    phraseAdvance($room, 5);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'writing')->assertJsonPath('game.me.draft', 'saved');
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/stop')->assertJsonPath('game', null);
    expect(RoomPlayer::findOrFail($players[0][0])->score)->toBe(65);
});

it('finishes blank tours and stops at the point target after revealing authors', function (bool $blank) {
    [$room, $players] = phraseSetup(2);
    $room->update(['point_target' => 50]);
    if ($blank) {
        foreach ($players as [$id, $token]) {
            phrasePost($room, $token, 'submit', ['suffix' => ''])->assertOk();
        }
    } else {
        $entries = phraseVoting($room, $players);
        phrasePost($room, $players[1][1], 'vote', ['choice' => $entries[$players[0][0]]])->assertOk();
        phraseAdvance($room, 30);
    }
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'reveal');
    phraseAdvance($room, 3);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', $blank ? 'writing' : 'results');
})->with([true, false]);

it('pauses on exhausted prefixes and resumes with explicit repeats without losing scores', function () {
    [$room, $players] = phraseSetup(2);
    $entries = phraseVoting($room, $players);
    phrasePost($room, $players[1][1], 'vote', ['choice' => $entries[$players[0][0]]])->assertOk();
    phraseAdvance($room, 30);
    $game = Game::where('room_id', $room->id)->sole();
    Content::where('id', '!=', $game->state['round']['content_id'])->update(['published' => false]);
    phraseAdvance($room, 3);
    $one = $players[0][1];
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.exhausted', true)->assertJsonPath('game.phase', 'paused');
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => $game->settings['packs'], 'allow_repeats' => true])->assertOk();
    phraseAdvance($room, 5);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    expect(RoomPlayer::findOrFail($players[0][0])->score)->toBe(65);
});

it('uses the validated Unicode presentation durations', function (int $length, float $seconds) {
    expect(app(PhraseGame::class)->displayDuration(str_repeat('é', $length)))->toBe($seconds);
})->with([[0, 5.0], [40, 5.0], [80, 7.0], [120, 9.0], [160, 11.0], [180, 12.0], [390, 12.0]]);

it('keeps late arrivals waiting and freezes anonymous presentation across a screen outage', function () {
    [$room, $players] = phraseSetup(2);
    [, $late] = enterRoom($room, 'Pat');
    phrasePost($room, $late, 'draft', ['suffix' => 'late'])->assertForbidden();
    foreach ($players as [$id, $token]) {
        phrasePost($room, $token, 'submit', ['suffix' => 'texte'])->assertOk();
    }
    phraseAdvance($room, 2);
    $room->update(['screen_seen_at' => now()->subSeconds(16)]);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'paused');
    phraseAdvance($room, 20);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'resuming');
    phraseAdvance($room, 5);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.phase', 'presenting')->assertJsonMissingPath('game.round.entries.0.author');
    expect(Game::where('room_id', $room->id)->sole()->state['deadline'])->toBe(app(GameEngine::class)->time() + 3.0);
    phraseAdvance($room, 13);
    $this->withCookie('plummo_player_'.$room->code, $late)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', false);
    phraseAdvance($room, 30);
    phraseAdvance($room, 3);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.me.eligible', true);
});

it('ends voting when all players with an available choice have voted', function () {
    [$room, $players] = phraseSetup();
    phrasePost($room, $players[0][1], 'submit', ['suffix' => 'seule proposition'])->assertOk();
    phrasePost($room, $players[1][1], 'submit', ['suffix' => ''])->assertOk();
    phrasePost($room, $players[2][1], 'submit', ['suffix' => ''])->assertOk();
    phraseAdvance($room, 5);
    $entry = Game::where('room_id', $room->id)->sole()->state['round']['entries'][0];
    phrasePost($room, $players[1][1], 'vote', ['choice' => $entry['id']])->assertOk();
    phrasePost($room, $players[2][1], 'vote', ['choice' => $entry['id']])->assertJsonPath('game.phase', 'reveal');
    expect(RoomPlayer::findOrFail($players[0][0])->score)->toBe(130);
});
