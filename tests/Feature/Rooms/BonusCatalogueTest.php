<?php

use App\Models\Game;
use App\Models\RoomPlayer;
use App\Services\GameBonuses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);
beforeEach(fn () => $this->withCredentials()->freezeTime());

function launchCatalogueBonus($test, $room, $member, $game, string $kind, string $id = 'catalogue'): void
{
    giveTestBonus($game, $member[0], $kind, $id);
    $test->withCookie('plummo_player_'.$room->code, $member[1])->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => $game->fresh()->state['number'], 'item_id' => $id])->assertOk();
}

it('lets a shield absorb only the next attack and lets defenses work after attacking', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    launchCatalogueBonus($this, $room, $members[1], $game, 'shield');
    launchCatalogueBonus($this, $room, $members[0], $game, 'bolt');
    $effects = $game->fresh()->state['bonuses']['effects'];
    expect(collect($effects)->where('target', $members[1][0])->where('kind', 'bolt'))->toHaveCount(0);
    expect(collect($effects)->where('target', $members[2][0])->where('kind', 'bolt'))->toHaveCount(1);
    launchCatalogueBonus($this, $room, $members[2], $game, 'dice');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('target', $members[1][0])->where('kind', 'dice'))->toHaveCount(1);
    launchCatalogueBonus($this, $room, $members[0], $game, 'soap');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('target', $members[0][0]))->toHaveCount(0);
})->group('bonuses');

it('reflects an attack only to its sender without a boomerang loop', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    launchCatalogueBonus($this, $room, $members[1], $game, 'boomerang');
    launchCatalogueBonus($this, $room, $members[0], $game, 'boomerang');
    launchCatalogueBonus($this, $room, $members[0], $game, 'bolt');
    $effects = collect($game->fresh()->state['bonuses']['effects']);
    expect($effects->where('target', $members[0][0])->where('kind', 'bolt'))->toHaveCount(1);
    expect($effects->where('target', $members[1][0])->where('kind', 'bolt'))->toHaveCount(0);
    expect($effects->where('target', $members[2][0])->where('kind', 'bolt'))->toHaveCount(1);
})->group('bonuses');

it('blocks attacks under a lock but leaves soap usable and keeps rejected objects', function () {
    [$room, $members, $game] = bonusQuiz();
    launchCatalogueBonus($this, $room, $members[0], $game, 'lock');
    giveTestBonus($game, $members[1][0], 'bolt');
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertConflict();
    expect($game->fresh()->state['bonuses']['inventory'][$members[1][0]])->toHaveCount(1);
    launchCatalogueBonus($this, $room, $members[1], $game, 'soap');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('target', $members[1][0]))->toHaveCount(0);
})->group('bonuses');

it('gives leaders an object too and never overwrites a third waiting object', function () {
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['scores'] = [$members[0][0] => 100, $members[1][0] => 0];
    $state['bonuses']['pending'][$members[1][0]] = ['id' => 'waiting', 'kind' => 'shield'];
    $game->update(['state' => $state]);
    $next = app(GameBonuses::class)->transition($game, [...$state, 'number' => 2], 'active');
    expect($next['bonuses']['inventory'][$members[0][0]])->toHaveCount(2);
    expect($next['bonuses']['pending'][$members[1][0]]['id'])->toBe('waiting');
})->group('bonuses');

it('extends and reduces only the active deadline with a reaction floor', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    $deadline = $game->state['deadline'];
    launchCatalogueBonus($this, $room, $members[0], $game, 'extension');
    expect($game->fresh()->state['deadline'])->toBe($deadline + 6);
    $this->travel(25)->seconds();
    $room->update(['screen_seen_at' => now()]);
    RoomPlayer::where('room_id', $room->id)->update(['last_seen_at' => now()]);
    launchCatalogueBonus($this, $room, $members[1], $game, 'leak');
    expect($game->fresh()->state['deadline'])->toBe((float) now()->format('U.u') + 8);
})->group('bonuses');

it('freezes the visible clock while answers remain available and can freeze again after expiry', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    launchCatalogueBonus($this, $room, $members[0], $game, 'freeze');
    $this->travel(3)->seconds();
    $view = $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect($view->json('game.bonuses.clock.frozenValue'))->toBe(30);
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->travel(4)->seconds();
    launchCatalogueBonus($this, $room, $members[2], $game, 'freeze');
    expect($game->fresh()->state['bonuses']['clock']['frozenUntil'])->toBe((float) now()->format('U.u') + 6);
})->group('bonuses');

it('repays borrowed time once on the next round and preserves early completion', function () {
    [$room, $members, $game] = bonusQuiz();
    launchCatalogueBonus($this, $room, $members[0], $game, 'borrow');
    foreach ($members as [$id, $cookie]) {
        $this->withCookie('plummo_player_'.$room->code, $cookie)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    }
    expect($game->fresh()->state['phase'])->toBe('reveal');
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $state = $game->fresh()->state;
    expect($state['number'])->toBe(2);
    expect($state['deadline'])->toBe((float) now()->format('U.u') + 24);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect($game->fresh()->state['deadline'])->toBe($state['deadline']);
    expect($game->fresh()->state['bonuses']['debt'])->toBe(0);
})->group('bonuses');

it('arms the last breath once without allowing repeated extensions or uncapped time', function () {
    [$room, $members, $game] = bonusQuiz();
    launchCatalogueBonus($this, $room, $members[0], $game, 'last_breath');
    $this->travel(28)->seconds();
    $room->update(['screen_seen_at' => now()]);
    RoomPlayer::where('room_id', $room->id)->update(['last_seen_at' => now()]);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $deadline = $game->fresh()->state['deadline'];
    expect($deadline)->toBe((float) now()->format('U.u') + 8);
    $this->travel(6)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect($game->fresh()->state['deadline'])->toBe($deadline);
    expect($game->fresh()->state['bonuses']['clock']['lastUsed'])->toBeTrue();
})->group('bonuses');

it('gives pinata objects privately and washes existing objects without losing a pending object', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    $state = $game->state;
    $state['bonuses']['inventory'][$members[1][0]] = [['id' => 'keep-a', 'kind' => 'shield'], ['id' => 'keep-b', 'kind' => 'dice']];
    $game->update(['state' => $state]);
    launchCatalogueBonus($this, $room, $members[0], $game, 'pinata');
    $before = $game->fresh()->state['bonuses'];
    expect($before['pending'][$members[1][0]])->not->toBeNull();
    launchCatalogueBonus($this, $room, $members[2], $game, 'washer');
    $after = $game->fresh()->state['bonuses'];
    expect($after['pending'][$members[1][0]])->toBe($before['pending'][$members[1][0]]);
    expect($after['inventory'][$members[1][0]])->toHaveCount(2);
    expect($after['inventory'][$members[1][0]][0]['id'])->not->toBe('keep-a');
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.bonuses.inventory', [])->assertJsonPath('game.bonuses.pending', null);
})->group('bonuses');

it('caps accumulated extensions and ignores timer objects after reveal', function () {
    [$room, $members, $game] = bonusQuiz(true, 6);
    $deadline = $game->state['deadline'];
    foreach ($members as $member) {
        launchCatalogueBonus($this, $room, $member, $game, 'extension');
    }
    expect($game->fresh()->state['deadline'])->toBe($deadline + 15);
    foreach ($members as [$id, $cookie]) {
        $this->withCookie('plummo_player_'.$room->code, $cookie)->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    }
    giveTestBonus($game, $members[0][0], 'freeze');
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertConflict();
})->group('bonuses');

it('extends temporary opponent effects under an alarm and lets soap clear them', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    launchCatalogueBonus($this, $room, $members[0], $game, 'bolt');
    launchCatalogueBonus($this, $room, $members[2], $game, 'alarm');
    $effects = collect($game->fresh()->state['bonuses']['effects']);
    expect($effects->where('target', $members[1][0])->where('kind', 'bolt')->first()['expiresAt'])->toBe((float) now()->format('U.u') + 6);
    launchCatalogueBonus($this, $room, $members[1], $game, 'soap');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('target', $members[1][0]))->toHaveCount(0);
})->group('bonuses');

it('keeps giant and clumsy brush geometry stable across retries and leaves erasers unchanged', function () {
    $room = openRoom();
    $members = [enterRoom($room, 'Artiste'), enterRoom($room, 'Un'), enterRoom($room, 'Deux')];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => [drawingPack()], 'rounds' => 1, 'duration' => 90, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->postJson('/rooms/'.$room->code.'/drawing/choose', [...$values, 'choice' => 0])->assertOk();
    launchCatalogueBonus($this, $room, $members[1], $game, 'giant');
    launchCatalogueBonus($this, $room, $members[2], $game, 'clumsy');
    $stroke = [...$values, 'revision' => 0, 'id' => 1, 'offset' => 0, 'color' => '#35236b', 'width' => 4, 'points' => [[0.1, 0.1], [0.2, 0.2]]];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/drawing/stroke', $stroke)->assertOk()->assertJsonPath('game.round.canvas.0.width', 12);
    $before = $game->fresh()->state['round']['canvas'];
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', $stroke)->assertOk();
    expect($game->fresh()->state['round']['canvas'])->toBe($before);
    expect($before[0]['points'])->not->toBe($stroke['points']);
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', [...$stroke, 'id' => 2, 'tool' => 'erase'])->assertOk()->assertJsonPath('game.round.canvas.1.width', 4)->assertJsonPath('game.round.canvas.1.points', $stroke['points']);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonMissingPath('game.round.canvas.0.input_points')->assertJsonMissingPath('game.round.canvas.0.clumsy');
})->group('bonuses');

it('deflects an artist prank without applying it to a player without a canvas', function () {
    $room = openRoom();
    $members = [enterRoom($room, 'Artiste'), enterRoom($room, 'Un'), enterRoom($room, 'Deux')];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => [drawingPack()], 'rounds' => 1, 'duration' => 90, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    $this->postJson('/rooms/'.$room->code.'/drawing/choose', ['game_id' => $game->id, 'round' => 1, 'choice' => 0])->assertOk();
    launchCatalogueBonus($this, $room, $members[0], $game, 'boomerang');
    launchCatalogueBonus($this, $room, $members[1], $game, 'giant');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('kind', 'giant'))->toHaveCount(0);
    expect(collect($game->fresh()->state['bonuses']['launches'])->where('reaction', true)->first()['deflected'])->toBeTrue();
    launchCatalogueBonus($this, $room, $members[2], $game, 'clumsy');
    expect(collect($game->fresh()->state['bonuses']['effects'])->where('target', $members[0][0])->where('kind', 'clumsy'))->toHaveCount(1);
})->group('bonuses');

it('supports each new object in its compatible modes and keeps inventories off the shared display', function (string $type, string $kind) {
    $room = openRoom();
    if ($type === 'blind_test') {
        Storage::fake('local');
        Storage::disk('local')->put('audio/test.wav', 'prepared audio');
        $this->postJson('/rooms/'.$room->code.'/screen-presence', ['audio_ready' => true])->assertOk();
    }
    $members = [enterRoom($room, 'Artiste'), enterRoom($room, 'Un'), enterRoom($room, 'Deux')];
    $pack = match ($type) {
        'quiz' => quizPack(), 'blind_test' => blindPack(), 'phrase' => phrasePack(), 'drawing' => drawingPack(100)
    };
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => $type, 'packs' => [$pack], 'rounds' => 5, 'duration' => 60, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    if ($type === 'drawing') {
        $this->postJson('/rooms/'.$room->code.'/drawing/choose', ['game_id' => $game->id, 'round' => 1, 'choice' => 0])->assertOk();
    }
    launchCatalogueBonus($this, $room, $members[1], $game, $kind);
    $state = $game->fresh()->state;
    expect($state['bonuses']['launches'][0]['kind'])->toBe($kind);
    expect($state['bonuses']['inventory'][$members[1][0]])->toHaveCount($kind === 'pinata' ? 1 : 0);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.bonuses.inventory', [])->assertJsonPath('game.bonuses.pending', null);
    expect($state['round']['participants'])->toHaveCount(3);
})->with(function () {
    $common = ['shield', 'soap', 'boomerang', 'pinata', 'washer', 'lock', 'mystery', 'bubbles', 'photobomb', 'jelly', 'strike', 'leak', 'freeze', 'extension', 'watch', 'overtime', 'last_breath', 'alarm', 'borrow'];
    $cases = [];
    foreach (['quiz', 'blind_test', 'phrase', 'drawing'] as $type) {
        foreach ($common as $kind) {
            $cases[$type.'-'.$kind] = [$type, $kind];
        }
    }
    foreach (['quiz' => ['missing_word', 'alphabet', 'curtain'], 'blind_test' => ['curtain', 'scratch', 'speed', 'singer'], 'phrase' => ['translator', 'advert'], 'drawing' => ['giant', 'mirror', 'clumsy']] as $type => $kinds) {
        foreach ($kinds as $kind) {
            $cases[$type.'-'.$kind] = [$type, $kind];
        }
    }

    return $cases;
})->group('bonuses');

it('extends an existing freeze continuously and reflects other clock changes in its visible value', function () {
    [$room, $members, $game] = bonusQuiz(true, 4);
    launchCatalogueBonus($this, $room, $members[0], $game, 'freeze');
    $until = $game->fresh()->state['bonuses']['clock']['frozenUntil'];
    $this->travel(2)->seconds();
    launchCatalogueBonus($this, $room, $members[1], $game, 'freeze');
    expect($game->fresh()->state['bonuses']['clock']['frozenUntil'])->toBe($until + 6);
    expect($game->fresh()->state['bonuses']['clock']['frozenValue'])->toBe(30);
    launchCatalogueBonus($this, $room, $members[2], $game, 'leak');
    expect($game->fresh()->state['bonuses']['clock']['frozenValue'])->toBe(24);
})->group('bonuses');

it('keeps freeze badges aligned and preserves effects while waiting for an absent artist', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    launchCatalogueBonus($this, $room, $members[0], $game, 'freeze');
    launchCatalogueBonus($this, $room, $members[1], $game, 'overtime');
    $state = $game->fresh()->state;
    expect(collect($state['bonuses']['effects'])->firstWhere('kind', 'freeze')['expiresAt'])->toBe($state['bonuses']['clock']['frozenUntil']);
    $state['phase'] = 'artist_missing';
    $paused = app(GameBonuses::class)->transition($game->fresh(), $state, 'active');
    $game->update(['state' => $paused]);
    $this->travel(15)->seconds();
    $state = [...$paused, 'phase' => 'answer'];
    $resumed = app(GameBonuses::class)->transition($game->fresh(), $state, 'active');
    expect($resumed['bonuses']['clock']['frozenUntil'])->toBe((float) now()->format('U.u') + 6);
    expect(collect($resumed['bonuses']['effects'])->firstWhere('kind', 'freeze')['expiresAt'])->toBe($resumed['bonuses']['clock']['frozenUntil']);
})->group('bonuses');

it('ignores departed and excluded scores when granting catchup objects and keeps weights private', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    $state = $game->state;
    $state['scores'] = [$members[0][0] => 100, $members[1][0] => 100, $members[2][0] => 1000, 999999 => 2000];
    $state['excluded'] = [$members[2][0]];
    $state['bonuses']['inventory'] = [];
    $state['bonuses']['pending'] = [];
    $game->update(['state' => $state]);
    $next = app(GameBonuses::class)->transition($game, [...$state, 'number' => 2], 'active');
    foreach ([$members[0][0], $members[1][0]] as $id) {
        expect($next['bonuses']['inventory'][$id])->toHaveCount(1);
        expect(array_keys($next['bonuses']['inventory'][$id][0]))->toBe(['id', 'kind']);
    }
    expect($next['bonuses']['inventory'])->not->toHaveKeys([$members[2][0], 999999]);
});
