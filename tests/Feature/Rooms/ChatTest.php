<?php

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

function chatPost(Room $room, string $token, string $message = 'Salut !', ?Game $game = null)
{
    return test()->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/chat', ['message' => $message, 'game_id' => $game?->id, 'round' => $game?->state['number']]);
}
function chatGame(string $type): array
{
    $room = openRoom();
    $players = [enterRoom($room), enterRoom($room, 'Alex'), enterRoom($room, 'Sam')];
    $pack = match ($type) {
        'drawing' => drawingPack(), 'phrase' => phrasePack(), default => quizPack()
    };
    test()->withCookie('plummo_player_'.$room->code, $players[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => $type, 'packs' => [$pack], 'rounds' => $type === 'quiz' ? 5 : 1, 'duration' => 60])->assertCreated();

    return [$room, $players, Game::where('room_id', $room->id)->sole()];
}

it('publishes one escaped text bubble per connected player for exactly five seconds', function () {
    $this->travelTo(now()->microsecond(750000));
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('canChat', true);
    $text = '<b>'.str_repeat('é', 73).'</b>';
    chatPost($room, $token, $text)->assertOk()->assertJsonPath('room.players.0.chat.message', $text)->assertJsonPath('room.players.0.chat.expiresAt', app(GameEngine::class)->time() + 5);
    chatPost($room, $token)->assertUnprocessable()->assertJsonValidationErrors('message');
    $this->travel(3)->seconds();
    chatPost($room, $token, 'Deuxième')->assertOk()->assertJsonPath('room.players.0.chat.message', 'Deuxième');
    $this->travel(4)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('room.players.0.chat.message', 'Deuxième');
    $this->travel(1)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('room.players.0.chat', null);
    expect(RoomPlayer::findOrFail($id)->score)->toBe(0);
});

it('validates messages and rejects unknown or absent players', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    foreach (['', '   ', str_repeat('é', 81)] as $text) {
        chatPost($room, $token, $text)->assertUnprocessable()->assertJsonValidationErrors('message');
    }
    chatPost($room, 'unknown')->assertForbidden();
    RoomPlayer::findOrFail($id)->update(['disconnected_at' => now()]);
    chatPost($room, $token)->assertForbidden();
    RoomPlayer::findOrFail($id)->update(['disconnected_at' => null, 'waiting' => true]);
    chatPost($room, $token)->assertForbidden();
    RoomPlayer::findOrFail($id)->update(['waiting' => false, 'left_at' => now()]);
    chatPost($room, $token)->assertForbidden();
});

it('allows chat after a definitive quiz answer and pause but rejects delayed messages in the next round', function () {
    [$room, $players, $game] = chatGame('quiz');
    $token = $players[0][1];
    chatPost($room, $token, 'Trop tôt', $game)->assertForbidden();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk()->assertJsonPath('canChat', true);
    chatPost($room, $token, 'Fini', $game)->assertOk()->assertJsonPath('game.round.correct', null);
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertJsonPath('canChat', true);
    $this->travel(3)->seconds();
    chatPost($room, $players[1][1], 'Pause', $game)->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game/resume')->assertOk();
    $this->travel(5)->seconds();
    $room->update(['screen_seen_at' => now()]);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    foreach (array_slice($players, 1) as [$id, $other]) {
        $this->withCookie('plummo_player_'.$room->code, $other)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    }
    $this->travel(3)->seconds();
    chatPost($room, $token, 'Ancienne manche', $game)->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('canChat', false);
    chatPost($room, $token, 'Ancien lobby')->assertConflict();
});

it('allows spectators and guessers waiting for selection, but keeps the artist and unsolved guessers at their controls', function () {
    [$room, $players, $game] = chatGame('drawing');
    [$artist, $one] = $players[0];
    [, $two] = $players[1];
    chatPost($room, $one, 'Je choisis', $game)->assertForbidden();
    chatPost($room, $two, 'J’attends', $game)->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/choose', ['game_id' => $game->id, 'round' => 1, 'choice' => 0])->assertOk();
    $word = $game->refresh()->state['round']['word'];
    $this->travel(3)->seconds();
    chatPost($room, $one, 'Je dessine', $game)->assertForbidden();
    chatPost($room, $two, 'Je cherche', $game)->assertForbidden();
    $this->postJson('/rooms/'.$room->code.'/drawing/guess', ['game_id' => $game->id, 'round' => 1, 'guess' => $word])->assertOk()->assertJsonPath('canChat', true);
    chatPost($room, $two, 'Trouvé', $game)->assertOk();
    [, $late] = enterRoom($room, 'Pat');
    chatPost($room, $late, 'Au prochain tour', $game)->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    chatPost($room, $players[2][1], 'Avant de passer', $game)->assertForbidden();
    $this->postJson('/rooms/'.$room->code.'/drawing/skip', ['game_id' => $game->id, 'round' => 1])->assertOk()->assertJsonPath('canChat', false);
    chatPost($room, $players[2][1], 'Je cherche encore', $game)->assertForbidden();
    $this->travel(3)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/drawing/skip', ['game_id' => $game->id, 'round' => 1])->assertOk()->assertJsonPath('canChat', true);
    chatPost($room, $two, 'Trouvé et voté', $game)->assertOk();
});

it('opens chat after writing and voting including when only the own sentence is available', function () {
    [$room, $players, $game] = chatGame('phrase');
    $one = $players[0][1];
    chatPost($room, $one, 'Écriture', $game)->assertForbidden();
    foreach ($players as $index => [$id, $token]) {
        $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/phrases/submit', ['game_id' => $game->id, 'round' => 1, 'suffix' => $index === 0 ? 'ma phrase' : ''])->assertOk()->assertJsonPath('canChat', true);
    }
    $this->travel(5)->seconds();
    chatPost($room, $one, 'Sans choix', $game)->assertOk()->assertJsonPath('game.phase', 'voting');
    chatPost($room, $players[1][1], 'Je dois voter', $game)->assertForbidden();
    $entry = $game->refresh()->state['round']['entries'][0]['id'];
    $this->postJson('/rooms/'.$room->code.'/phrases/vote', ['game_id' => $game->id, 'round' => 1, 'choice' => $entry])->assertOk()->assertJsonPath('canChat', true);
    chatPost($room, $players[1][1], 'Voté', $game)->assertOk();
});

it('allows chat after a skip vote if the absent artist never selected a word', function () {
    [$room, $players, $game] = chatGame('drawing');
    $this->withCookie('plummo_player_'.$room->code, $players[0][1])->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    chatPost($room, $players[1][1], 'Vote attendu', $game)->assertForbidden();
    $this->postJson('/rooms/'.$room->code.'/drawing/skip', ['game_id' => $game->id, 'round' => 1])->assertOk()->assertJsonPath('canChat', true);
    chatPost($room, $players[1][1], 'Pas de mot à deviner', $game)->assertOk();
});
