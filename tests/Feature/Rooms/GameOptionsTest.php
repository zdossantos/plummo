<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('offers only packs matching the game and games matching connected player count', function () {
    $this->withCredentials();
    $room = openRoom();
    [, $token] = enterRoom($room);
    $quiz = quizPack();
    $drawing = drawingPack();
    phrasePack();
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'quiz'])->assertOk()->assertJsonCount(1, 'packs')->assertJsonPath('packs.0.id', $quiz)->assertJsonPath('games', ['quiz', 'blind_test']);
    enterRoom($room, 'Alex');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'drawing'])->assertOk()->assertJsonCount(1, 'packs')->assertJsonPath('packs.0.id', $drawing)->assertJsonPath('games', ['quiz', 'blind_test', 'drawing']);
});
