<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Game;
use App\Models\Pack;
use App\Models\RoomPlayer;
use App\Models\Tag;
use App\Services\RoomContentCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials());

it('uses the union of every pack without duplicates or unpacked contents', function (ContentType $type) {
    $tags = collect(range(1, 4))->map(fn ($number) => Tag::create(['name' => 'Tag '.$number]));
    $packs = $tags->map(function ($tag) {
        $pack = Pack::create(['name' => $tag->name]);
        $pack->tags()->sync([$tag->id]);

        return $pack;
    });
    $contents = $tags->map(function ($tag) use ($type) {
        $content = Content::create(['type' => $type, 'published' => true, 'payload' => []]);
        $content->tags()->sync([$tag->id]);

        return $content;
    });
    $contents[0]->tags()->sync($tags->pluck('id')->all());
    $draft = Content::create(['type' => $type, 'published' => false, 'payload' => []]);
    $draft->tags()->sync($tags->pluck('id')->all());
    Content::create(['type' => $type, 'published' => true, 'payload' => []]);
    $other = Content::create(['type' => $type === ContentType::Quiz ? ContentType::Drawing : ContentType::Quiz, 'published' => true, 'payload' => []]);
    $other->tags()->sync($tags->pluck('id')->all());
    $room = openRoom();
    $catalog = app(RoomContentCatalog::class);
    expect($catalog->availability($room, $type, []))->toBe(['total' => 4, 'unseen' => 4]);
    expect($catalog->availability($room, $type, [$packs[1]->id]))->toBe(['total' => 2, 'unseen' => 2]);
    expect($catalog->reveal($room, $type, [], 4)->modelKeys())->toEqualCanonicalizing($contents->pluck('id')->all());
    expect($catalog->availability($room, $type, []))->toBe(['total' => 4, 'unseen' => 0]);
    expect(fn () => $catalog->reveal($room, $type, [], 1))->toThrow(ValidationException::class);
    expect($catalog->reveal($room, $type, [], 4, true)->modelKeys())->toEqualCanonicalizing($contents->pluck('id')->all());
    expect(DB::table('room_content_history')->where('room_id', $room->id)->count())->toBe(4);
})->with(ContentType::cases());

it('returns no playable contents when no pack admits them', function () {
    Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => []]);
    Pack::create(['name' => 'Empty']);
    expect(app(RoomContentCatalog::class)->availability(openRoom(), ContentType::Quiz, []))->toBe(['total' => 0, 'unseen' => 0]);
});

it('previews all packs for omitted or empty selection and limits an explicit selection', function (array $selection) {
    $room = openRoom();
    [, $token] = enterRoom($room);
    $pack = quizPack();
    $tag = Tag::create(['name' => 'Extra']);
    $extra = Pack::create(['name' => 'Extra']);
    $extra->tags()->sync([$tag->id]);
    $content = Content::create(['type' => ContentType::Quiz, 'published' => true, 'payload' => []]);
    $content->tags()->sync([$tag->id]);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'quiz', ...$selection])->assertOk()->assertJsonPath('availability', ['total' => 6, 'unseen' => 6]);
    $this->postJson('/rooms/'.$room->code.'/game-options', ['type' => 'quiz', 'packs' => [$pack]])->assertOk()->assertJsonPath('availability', ['total' => 5, 'unseen' => 5]);
})->with(['omitted' => [[]], 'empty' => [['packs' => []]]]);

it('starts a quiz with an empty pack selection and records the revealed content', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    quizPack();
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, ['type' => 'quiz', 'packs' => [], 'rounds' => 5, 'duration' => 30])->assertCreated()->assertJsonPath('game.phase', 'answer')->assertJsonPath('game.settings.packs', []);
    $this->assertDatabaseCount('room_content_history', 1);
});

it('refuses an empty selection when the unseen catalogue cannot cover the game', function () {
    $room = openRoom();
    [, $token] = enterRoom($room);
    quizPack();
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, ['type' => 'quiz', 'packs' => [], 'rounds' => 6, 'duration' => 30])->assertUnprocessable()->assertJsonValidationErrors('packs');
    $this->assertDatabaseCount('games', 0);
    $this->assertDatabaseCount('room_content_history', 0);
});

it('recovers with all packs only after explicit repeats when their unseen contents are exhausted', function () {
    $room = openRoom();
    [$chief, $token] = enterRoom($room);
    $pack = quizPack();
    $this->withCookie('plummo_player_'.$room->code, $token)->startReadyGame($room, ['type' => 'quiz', 'packs' => [$pack], 'rounds' => 5, 'duration' => 30])->assertCreated();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $revealed = Game::where('room_id', $room->id)->sole()->state['round']['content_id'];
    Content::where('id', '!=', $revealed)->update(['published' => false]);
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', 'paused')->assertJsonPath('game.exhausted', true);
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => [], 'allow_repeats' => false])->assertUnprocessable()->assertJsonValidationErrors('packs');
    $this->postJson('/rooms/'.$room->code.'/game-recovery', ['packs' => [], 'allow_repeats' => true])->assertOk()->assertJsonPath('game.phase', 'resuming')->assertJsonPath('game.settings.packs', []);
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    expect(RoomPlayer::findOrFail($chief)->score)->toBe(65);
    $this->assertDatabaseCount('room_content_history', 1);
});
