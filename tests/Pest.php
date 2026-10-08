<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\Room;
use App\Models\Tag;
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

function quizPack(): int
{
    $tag = Tag::create(['name' => 'Quiz']);
    $pack = Pack::create(['name' => 'Quiz']);
    $pack->tags()->sync([$tag->id]);
    for ($i = 0; $i < 5; $i++) {
        $content = Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => ['question' => 'Question '.$i, 'choices' => ['A', 'B', 'C', 'D'], 'correct' => 0]]);
        $content->tags()->sync([$tag->id]);
    }

    return $pack->id;
}

function quizAnswer(Room $room, int $choice): array
{
    $game = Game::where('room_id', $room->id)->latest('id')->firstOrFail();

    return ['choice' => $choice, 'game_id' => $game->id, 'round' => $game->state['number']];
}
