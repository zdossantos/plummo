<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use App\Models\Tag;
use App\Services\RoomContentCatalog;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

function historyCatalog(): array
{
    $tag = Tag::create(['name' => 'Animaux']);
    $packs = collect(['Animaux', 'Nature'])->map(function ($name) use ($tag) {
        $pack = Pack::create(['name' => $name]);
        $pack->tags()->sync([$tag->id]);

        return $pack->id;
    })->all();
    $contents = collect(['Chat', 'Chien', 'Lapin'])->map(function ($word) use ($tag) {
        $content = Content::create(['type' => ContentType::Drawing, 'published' => true, 'payload' => ['word' => $word]]);
        $content->tags()->sync([$tag->id]);

        return $content;
    });

    return [$packs, $contents];
}

it('deduplicates overlapping packs and remembers all three revealed words only for their room', function () {
    [$packs, $contents] = historyCatalog();
    $room = openRoom();
    $service = app(RoomContentCatalog::class);
    expect($service->availability($room, ContentType::Drawing, $packs))->toBe(['total' => 3, 'unseen' => 3]);
    $selected = $service->reveal($room, ContentType::Drawing, $packs, 3);
    expect($selected->modelKeys())->toHaveCount(3)->and(array_unique($selected->modelKeys()))->toHaveCount(3);
    expect($service->availability($room, ContentType::Drawing, [$packs[1]]))->toBe(['total' => 3, 'unseen' => 0]);
    expect($service->availability(app(RoomService::class)->create(), ContentType::Drawing, $packs)['unseen'])->toBe(3);
    $room->players()->update(['score' => 0]);
    expect($service->availability($room, ContentType::Drawing, $packs)['unseen'])->toBe(0);
});

it('requires explicit repeats on exhaustion without clearing history or returning duplicate choices', function () {
    [$packs] = historyCatalog();
    $room = openRoom();
    $service = app(RoomContentCatalog::class);
    $service->reveal($room, ContentType::Drawing, $packs, 3);
    expect(fn () => $service->reveal($room, ContentType::Drawing, $packs, 1))->toThrow(ValidationException::class);
    expect($service->reveal($room, ContentType::Drawing, $packs, 3, true)->modelKeys())->toHaveCount(3);
    expect(DB::table('room_content_history')->where('room_id', $room->id)->count())->toBe(3);
    expect(fn () => $service->reveal($room, ContentType::Drawing, $packs, 4, true))->toThrow(ValidationException::class);
});

it('preserves history when content disappears and deletes it when the room closes', function () {
    [$packs] = historyCatalog();
    $room = openRoom();
    app(RoomContentCatalog::class)->reveal($room, ContentType::Drawing, $packs, 1)->first()->delete();
    expect(DB::table('room_content_history')->where('room_id', $room->id)->count())->toBe(1);
    $room->delete();
    expect(DB::table('room_content_history')->where('room_id', $room->id)->count())->toBe(0);
});

it('prefers unseen content even after the chief authorizes repeats', function () {
    [$packs, $contents] = historyCatalog();
    $room = openRoom();
    $service = app(RoomContentCatalog::class);
    $seen = $service->reveal($room, ContentType::Drawing, $packs, 2)->modelKeys();
    $remaining = $contents->first(fn ($content) => ! in_array($content->id, $seen))->id;
    for ($i = 0; $i < 10; $i++) {
        DB::beginTransaction();
        try {
            expect($service->reveal($room, ContentType::Drawing, $packs, 1, true)->first()->id)->toBe($remaining);
        } finally {
            DB::rollBack();
        }
    }
});
