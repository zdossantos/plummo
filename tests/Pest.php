<?php

use App\Models\Room;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Browser');

function openRoom(): Room
{
    test()->withoutVite()->get('/')->assertRedirect();

    return Room::latest('id')->firstOrFail();
}

function enterRoom(Room $room, string $name = 'Camille', array $accessories = []): array
{
    test()->withCookie('plummo_player_'.$room->code, '');
    $response = test()->postJson('/rooms/'.$room->code.'/players', [
        'name' => $name, 'color' => 'violet', 'accessories' => $accessories,
    ])->assertCreated();
    $token = $response->getCookie('plummo_player_'.$room->code)->getValue();

    return [$response->json('me.id'), $token];
}
