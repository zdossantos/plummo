<?php

use App\Models\Room;
use App\Models\RoomPlayer;
use App\Services\RoomService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(fn () => $this->withCredentials());

it('creates a room when opening the shared screen and reuses it on refresh', function () {
    $this->withoutVite()->get('/')->assertRedirect();
    $room = Room::sole();
    $this->get('/')->assertRedirect('/screen/'.$room->code);
    expect(Room::count())->toBe(1);
    $this->get('/screen/'.$room->code)->assertInertia(fn (Assert $page) => $page
        ->component('rooms/Screen')->where('room.code', $room->code)->where('room.capacity', 8)
        ->where('room.players', [])->where('translations.rooms.waiting_first', __('rooms.waiting_first')));
});

it('allows joining by a normalized manual code and returns a locally generated QR', function () {
    $room = openRoom();
    $this->post('/join', ['code' => strtolower($room->code)])->assertRedirect('/join/'.$room->code);
    $this->get('/join/'.$room->code)->assertInertia(fn (Assert $page) => $page->component('rooms/Join'));
    $this->get('/rooms/'.$room->code.'/qr')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->post('/join', ['code' => 'ZZZZZZ'])->assertSessionHasErrors('code');
});

it('assigns the first participant as chief while allowing duplicate identities', function () {
    $room = openRoom();
    [$first] = enterRoom($room);
    [$second] = enterRoom($room);
    $state = $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect($first)->not->toBe($second);
    $state->assertJsonPath('room.chiefId', $first)->assertJsonCount(2, 'room.players');
    expect($state->getContent())->not->toContain('identity_hash')->not->toContain('last_seen_at');
});

it('rejects a ninth participant and leaves eight existing players untouched', function () {
    $room = openRoom();
    foreach (range(1, 8) as $i) {
        enterRoom($room, 'Joueur '.$i);
    }
    $this->withCookie('plummo_player_'.$room->code, '');
    $this->postJson('/rooms/'.$room->code.'/players', ['name' => 'Neuvième', 'color' => 'blue', 'accessories' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('room');
    expect($room->players()->count())->toBe(8);
});

it('validates customization on the server', function (array $attributes, string $field) {
    $room = openRoom();
    $this->postJson('/rooms/'.$room->code.'/players', [...['name' => 'Alex', 'color' => 'blue', 'accessories' => []], ...$attributes])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($room->players()->count())->toBe(0);
})->with([
    [['name' => '   '], 'name'],
    [['name' => str_repeat('a', 31)], 'name'],
    [['color' => 'unknown'], 'color'],
    [['accessories' => ['helmet']], 'accessories.0'],
    [['accessories' => ['cap', 'crown']], 'accessories'],
    [['accessories' => ['cap', 'cap']], 'accessories.0'],
]);

it('edits only the recognized participant and preserves points and chief role', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room);
    RoomPlayer::findOrFail($id)->update(['score' => 155]);
    $this->withCookie('plummo_player_'.$room->code, $token)
        ->patchJson('/rooms/'.$room->code.'/me', ['name' => 'Alex', 'color' => 'green', 'accessories' => ['crown', 'wand']])
        ->assertOk()->assertJsonPath('me.name', 'Alex')->assertJsonPath('me.score', 155)
        ->assertJsonPath('room.chiefId', $id);
    $this->withCookie('plummo_player_'.$room->code, 'forged')->patchJson('/rooms/'.$room->code.'/me', [
        'name' => 'Intrus', 'color' => 'violet', 'accessories' => [],
    ])->assertForbidden();
});

it('frees a place on voluntary leave and restores the same player on return', function () {
    $room = openRoom();
    [$id, $token] = enterRoom($room, 'Alex', ['cap']);
    RoomPlayer::findOrFail($id)->update(['score' => 220]);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('room.occupied', 0);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('me.status', 'left');
    $this->postJson('/rooms/'.$room->code.'/return')->assertOk()
        ->assertJsonPath('me.id', $id)->assertJsonPath('me.name', 'Alex')->assertJsonPath('me.score', 220)
        ->assertJsonPath('me.accessories', ['cap'])->assertJsonPath('me.status', 'connected');
    expect($room->players()->count())->toBe(1);
});

it('reserves a disconnected place for two minutes then permits another identity', function () {
    $room = openRoom();
    [$old] = enterRoom($room);
    $this->travel(16)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('room.occupied', 1)
        ->assertJsonPath('room.players.0.status', 'disconnected');
    $this->travel(120)->seconds();
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('room.occupied', 0);
    [$new] = enterRoom($room);
    expect($new)->not->toBe($old);
    expect($room->players()->count())->toBe(2);
});

it('restores the original chief after automatic relay but respects a voluntary transfer', function () {
    $room = openRoom();
    [$first, $token] = enterRoom($room);
    $this->travel(5)->seconds();
    [$second, $secondToken] = enterRoom($room);
    $this->travel(11)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $secondToken)->postJson('/rooms/'.$room->code.'/presence')
        ->assertJsonPath('room.chiefId', $second);
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/presence')
        ->assertJsonPath('room.chiefId', $first);
    $this->postJson('/rooms/'.$room->code.'/chief', ['playerId' => $second])->assertOk()
        ->assertJsonPath('room.chiefId', $second);
    $this->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/return')->assertJsonPath('room.chiefId', $second);
    $this->postJson('/rooms/'.$room->code.'/chief', ['playerId' => $first])->assertForbidden();
});

it('holds a recognized returning player outside a full room until a place is free', function () {
    $room = openRoom();
    [$old, $oldToken] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $oldToken)->postJson('/rooms/'.$room->code.'/leave');
    $lastToken = '';
    foreach (range(1, 8) as $i) {
        [, $lastToken] = enterRoom($room, 'Nouveau '.$i);
    }
    $this->withCookie('plummo_player_'.$room->code, $oldToken)->postJson('/rooms/'.$room->code.'/return')
        ->assertOk()->assertJsonPath('me.status', 'waiting')->assertJsonPath('me.id', $old);
    $this->withCookie('plummo_player_'.$room->code, $lastToken)->postJson('/rooms/'.$room->code.'/leave');
    $this->withCookie('plummo_player_'.$room->code, $oldToken)->postJson('/rooms/'.$room->code.'/presence')
        ->assertJsonPath('me.status', 'connected')->assertJsonPath('room.occupied', 8);
});

it('allows only the chief to close a confirmed room and erases all its data', function () {
    $room = openRoom();
    [$chief, $chiefToken] = enterRoom($room);
    [, $otherToken] = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $otherToken)->deleteJson('/rooms/'.$room->code, ['confirm' => true])
        ->assertForbidden();
    $this->withCookie('plummo_player_'.$room->code, $chiefToken)->deleteJson('/rooms/'.$room->code, ['confirm' => false])
        ->assertUnprocessable();
    $this->deleteJson('/rooms/'.$room->code, ['confirm' => true])->assertNoContent();
    expect(Room::find($room->id))->toBeNull();
    expect(RoomPlayer::find($chief))->toBeNull();
    $this->getJson('/rooms/'.$room->code.'/state')->assertNotFound();
});

it('expires a room only after thirty minutes with no connected players', function () {
    $this->travelTo(now()->microsecond(750000));
    $room = openRoom();
    [, $token] = enterRoom($room);
    foreach (range(1, 7) as $i) {
        $this->travel(5)->minutes();
        // Simulates regular heartbeats during lobby waiting, not a single delayed heartbeat.
        $room->players()->update(['last_seen_at' => now()]);
        $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    }
    $this->postJson('/rooms/'.$room->code.'/leave');
    $this->travel(29)->minutes();
    $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    $this->travel(1)->minutes();
    $this->getJson('/rooms/'.$room->code.'/state')->assertNotFound();
    expect(RoomPlayer::count())->toBe(0);
});

it('prunes expired rooms while preserving a lobby containing connected players', function () {
    $expired = openRoom();
    $expired->update(['empty_since' => now()->subMinutes(31)]);
    $active = app(RoomService::class)->create();
    enterRoom($active);
    $this->artisan('rooms:prune')->assertSuccessful();
    expect(Room::find($expired->id))->toBeNull();
    expect(Room::find($active->id))->not->toBeNull();
});

it('rejects malformed manual codes without a server error', function () {
    $this->post('/join', ['code' => ['invalid']])->assertSessionHasErrors('code');
});

it('creates a fresh room when the previous screen room has expired', function () {
    $room = openRoom();
    $room->update(['empty_since' => now()->subMinutes(31)]);
    $this->get('/')->assertRedirect();
    expect(Room::find($room->id))->toBeNull();
    expect(Room::count())->toBe(1);
});

it('uses the configured phone-reachable address for QR and manual entry', function () {
    config(['app.url' => 'http://192.168.1.20:8090']);
    $room = openRoom();
    $url = 'http://192.168.1.20:8090/join/'.$room->code;
    $writer = new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd));
    $this->get('/rooms/'.$room->code.'/qr')->assertContent($writer->writeString($url));
    $this->get('/screen/'.$room->code)->assertInertia(fn (Assert $page) => $page
        ->where('joinUrl', $url)->where('manualUrl', 'http://192.168.1.20:8090/join'));
});

it('rejects object-shaped accessory selections before exposing them to avatars', function () {
    $room = openRoom();
    $this->postJson('/rooms/'.$room->code.'/players', ['name' => 'Camille', 'color' => 'violet', 'accessories' => ['head' => 'cap']])
        ->assertUnprocessable()->assertJsonValidationErrors('accessories');
});

it('relays to the longest continuously connected player after a returning participant', function () {
    $room = openRoom();
    $owner = enterRoom($room);
    $earlier = enterRoom($room);
    $this->withCookie('plummo_player_'.$room->code, $earlier[1])->postJson('/rooms/'.$room->code.'/leave')->assertOk();
    $this->travel(1)->seconds();
    $continuous = enterRoom($room);
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $earlier[1])->postJson('/rooms/'.$room->code.'/return')->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $owner[1])->postJson('/rooms/'.$room->code.'/leave')
        ->assertJsonPath('room.chiefId', $continuous[0]);
});
