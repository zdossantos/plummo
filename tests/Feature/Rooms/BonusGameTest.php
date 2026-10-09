<?php

use App\Models\Game;
use App\Models\RoomPlayer;
use App\Services\GameBonuses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

beforeEach(fn () => $this->withCredentials());

function bonusQuiz(bool $enabled = true, int $players = 2): array
{
    $room = openRoom();
    $members = [];
    foreach (range(1, $players) as $index) {
        $members[] = enterRoom($room, 'Joueur '.$index);
    }
    test()->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'quiz', 'packs' => [quizPack()], 'rounds' => 5, 'duration' => 30, 'bonuses' => $enabled])->assertCreated();

    return [$room, $members, Game::where('room_id', $room->id)->sole()];
}

it('gives a compatible initial object only when enabled with opponents', function () {
    [$room, $members, $game] = bonusQuiz();
    $view = $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect($view->json('game.bonuses.inventory'))->toHaveCount(1);
    expect($view->json('game.bonuses.inventory.0.kind'))->toBeIn(['bolt', 'dice', 'squatter']);
    expect($game->state['bonuses']['inventory'][$members[1][0]])->toHaveCount(1);
})->group('bonuses');

it('keeps bonuses unavailable in solo or disabled games', function (bool $enabled, int $players) {
    [$room] = bonusQuiz($enabled, $players);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.enabled', false)->assertJsonPath('game.bonuses.inventory', []);
})->with([[false, 2], [true, 1]])->group('bonuses');

it('consumes an object once and applies the effect to all opponents without exposing their inventory', function () {
    [$room, $members, $game] = bonusQuiz(true, 3);
    $item = $this->postJson('/rooms/'.$room->code.'/presence')->json('game.bonuses.inventory.0');
    $values = ['game_id' => $game->id, 'round' => 1, 'item_id' => $item['id']];
    $response = $this->postJson('/rooms/'.$room->code.'/bonuses', $values)->assertOk();
    expect($response->json('game.bonuses.inventory'))->toBe([]);
    expect($response->json('game.bonuses.effects'))->toBe([]);
    $this->postJson('/rooms/'.$room->code.'/bonuses', $values)->assertConflict();
    $other = $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect($other->json('game.bonuses.effects'))->toHaveCount(1);
    expect(array_keys($other->json('game.bonuses')))->not->toContain('inventories');
    expect($other->json('game.bonuses.effects.0.targets'))->toBeNull();
})->group('bonuses');

it('refuses paused stale missing and incompatible objects without consuming them', function () {
    [$room, $members, $game] = bonusQuiz();
    $item = $this->postJson('/rooms/'.$room->code.'/presence')->json('game.bonuses.inventory.0');
    $values = ['game_id' => $game->id, 'round' => 1, 'item_id' => $item['id']];
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'round' => 2])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'game_id' => $game->id + 1])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'missing'])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/bonuses', $values)->assertConflict();
    expect(Game::findOrFail($game->id)->state['bonuses']['inventory'][$members[0][0]])->toHaveCount(1);
})->group('bonuses');

it('gives a catchup object to strict trailers once at the next round and preserves full pockets', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk();
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.round.number', 2);
    $current = Game::findOrFail($game->id);
    expect($current->state['bonuses']['inventory'][$members[0][0]])->toHaveCount(1);
    expect($current->state['bonuses']['inventory'][$members[1][0]])->toHaveCount(2);
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    expect(Game::findOrFail($game->id)->state['bonuses']['inventory'][$members[1][0]])->toHaveCount(2);
    expect(RoomPlayer::findOrFail($members[0][0])->score)->toBe(100);
})->group('bonuses');

it('does not give catchup objects to tied leaders', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->travel(3)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    foreach (Game::findOrFail($game->id)->state['bonuses']['inventory'] as $items) {
        expect($items)->toHaveCount(1);
    }
})->group('bonuses');

function giveTestBonus(Game $game, int $player, string $kind, string $id = 'test-object'): void
{
    $state = $game->fresh()->state;
    $state['bonuses']['inventory'][$player] = [['id' => $id, 'kind' => $kind]];
    $game->update(['state' => $state]);
}

it('expires timed effects and freezes their remaining duration during a manual pause', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    giveTestBonus($game, $members[0][0], 'bolt');
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertOk();
    $this->travel(1)->seconds();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->travel(5)->seconds();
    $this->postJson('/rooms/'.$room->code.'/game/resume')->assertOk();
    $this->travel(5)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/presence')->assertJsonCount(1, 'game.bonuses.effects');
    $this->travel(2)->seconds();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.effects', []);
})->group('bonuses');

it('refuses a second launch even with another object and clears the pocket when stopped', function () {
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['bonuses']['inventory'][$members[0][0]] = [['id' => 'one', 'kind' => 'bolt'], ['id' => 'two', 'kind' => 'dice']];
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'one'])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'two'])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/game/stop')->assertOk();
    expect($game->fresh()->state['bonuses']['inventory'])->toBe([]);
})->group('bonuses');

it('transforms only opponents validated phrases and keeps drafts and authors private', function (string $kind, string $expected) {
    $room = openRoom();
    $members = [enterRoom($room, 'Un'), enterRoom($room, 'Deux'), enterRoom($room, 'Trois')];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'phrase', 'packs' => [phrasePack()], 'rounds' => 1, 'duration' => 60, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    giveTestBonus($game, $members[0][0], $kind);
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/phrases/submit', [...$values, 'suffix' => 'Rire avec Robert'])->assertOk()->assertJsonPath('game.me.draft', 'Rire avec Robert');
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'test-object'])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/phrases/submit', [...$values, 'suffix' => 'Rire avec Robert'])->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $members[2][1])->postJson('/rooms/'.$room->code.'/phrases/submit', [...$values, 'suffix' => 'Rire avec Robert'])->assertOk();
    $state = $game->fresh()->state;
    foreach ($state['round']['entries'] as $entry) {
        expect($entry['text'])->toBe($state['round']['prompt'].' '.($entry['author'] === $members[0][0] ? 'Rire avec Robert' : $expected));
    }
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonMissingPath('game.round.entries.0.author')->assertJsonMissingPath('game.round.drafts')->assertJsonPath('game.bonuses.effects', []);
})->with([['accent', 'Wiwe avec Wobewt'], ['sneeze', 'Rire avec ATCHOUM ! Robert']])->group('bonuses');

it('imposes paint on new strokes without corrupting existing strokes or chunk retries', function () {
    $this->freezeTime();
    $room = openRoom();
    [$artist, $one] = enterRoom($room);
    [$other, $two] = enterRoom($room, 'Autre');
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/games', ['type' => 'drawing', 'packs' => [drawingPack()], 'rounds' => 1, 'duration' => 90, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->postJson('/rooms/'.$room->code.'/drawing/choose', [...$values, 'choice' => 0])->assertOk();
    $stroke = [...$values, 'revision' => 0, 'id' => 1, 'offset' => 0, 'color' => '#35236b', 'width' => 4, 'points' => [[0.1, 0.1], [0.2, 0.2]]];
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', $stroke)->assertOk();
    giveTestBonus($game, $other, 'paint');
    $this->withCookie('plummo_player_'.$room->code, $two)->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'test-object'])->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $one)->postJson('/rooms/'.$room->code.'/drawing/stroke', [...$stroke, 'id' => 2])->assertOk()->assertJsonPath('game.round.canvas.0.color', '#35236b')->assertJsonPath('game.round.canvas.1.color', '#f05a78');
    $this->travel(4)->seconds();
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', [...$stroke, 'id' => 2, 'offset' => 2, 'points' => [[0.3, 0.3]]])->assertOk();
    $this->postJson('/rooms/'.$room->code.'/drawing/stroke', [...$stroke, 'id' => 3])->assertOk()->assertJsonPath('game.round.canvas.2.color', '#35236b');
})->group('bonuses');

it('keeps a full pocket unchanged across another lost round and clears objects at the natural end', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $full = null;
    foreach (range(1, 5) as $round) {
        $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
        $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk();
        $this->travel(3)->seconds();
        $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
        $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.phase', $round === 5 ? 'results' : 'answer');
        $state = $game->fresh()->state;
        if ($round === 1) {
            $full = $state['bonuses']['inventory'][$members[1][0]];
        }
        if ($round > 1 && $round < 5) {
            expect($state['bonuses']['inventory'][$members[1][0]])->toBe($full);
        }
    }
    expect($game->fresh()->status)->toBe('finished');
    expect($game->fresh()->state['bonuses']['inventory'])->toBe([]);
    expect($game->fresh()->state['bonuses']['effects'])->toBe([]);
})->group('bonuses');

it('does not extend the same effect when another opponent launches it', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz(true, 3);
    giveTestBonus($game, $members[0][0], 'bolt', 'one');
    giveTestBonus($game, $members[1][0], 'bolt', 'two');
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'one'])->assertOk();
    $this->travel(1)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'two'])->assertOk();
    $this->travel(2)->seconds();
    $this->withCookie('plummo_player_'.$room->code, $members[2][1])->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.effects', []);
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/presence')->assertJsonCount(1, 'game.bonuses.effects');
})->group('bonuses');

it('broadcasts a launch cue once to the screen and all controllers without inventory details', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    giveTestBonus($game, $members[0][0], 'bolt');
    $response = $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertOk();
    $cue = $response->json('game.bonuses.launches.0');
    expect($cue)->not->toBeNull();
    expect($cue['kind'])->toBe('bolt');
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.bonuses.launches.0.id', $cue['id']);
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.launches.0.id', $cue['id']);
    $this->travel(8)->seconds();
    $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
    $this->postJson('/rooms/'.$room->code.'/presence')->assertJsonPath('game.bonuses.launches', []);
})->group('bonuses');

it('offers a third object privately and replaces only the chosen owned object', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['bonuses']['inventory'][$members[1][0]] = [['id' => 'first', 'kind' => 'bolt'], ['id' => 'second', 'kind' => 'dice']];
    $game->update(['state' => $state]);
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk();
    $this->travel(3)->seconds();
    $view = $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
    $pending = $view->json('game.bonuses.pending');
    expect($pending)->not->toBeNull();
    expect($view->json('game.bonuses.inventory'))->toHaveCount(2);
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonPath('game.bonuses.pending', null);
    $values = ['game_id' => $game->id, 'round' => 2, 'item_id' => $pending['id'], 'replace_id' => 'second'];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/bonuses/replace', $values)->assertConflict();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/bonuses/replace', [...$values, 'replace_id' => 'missing'])->assertConflict();
    $this->postJson('/rooms/'.$room->code.'/bonuses/replace', $values)->assertOk()->assertJsonPath('game.bonuses.inventory.0.id', 'first')->assertJsonPath('game.bonuses.inventory.1.id', $pending['id'])->assertJsonPath('game.bonuses.pending', null);
    $this->postJson('/rooms/'.$room->code.'/bonuses/replace', $values)->assertConflict();
})->group('bonuses');

it('can decline a pending object or move it into the place freed by a launch', function () {
    [$room, $members, $game] = bonusQuiz();
    $id = $members[0][0];
    $state = $game->state;
    $state['bonuses']['inventory'][$id] = [['id' => 'first', 'kind' => 'bolt'], ['id' => 'second', 'kind' => 'dice']];
    $state['bonuses']['pending'][$id] = ['id' => 'third', 'kind' => 'squatter'];
    $game->update(['state' => $state]);
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->postJson('/rooms/'.$room->code.'/bonuses/replace', [...$values, 'item_id' => 'third', 'replace_id' => null])->assertOk()->assertJsonPath('game.bonuses.pending', null)->assertJsonCount(2, 'game.bonuses.inventory');
    $state = $game->fresh()->state;
    $state['bonuses']['pending'][$id] = ['id' => 'fourth', 'kind' => 'squatter'];
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'first'])->assertOk()->assertJsonPath('game.bonuses.pending', null)->assertJsonPath('game.bonuses.inventory.0.id', 'second')->assertJsonPath('game.bonuses.inventory.1.id', 'fourth');
})->group('bonuses');

it('launches every compatible object with the expected targets and lifetime', function (string $type, string $kind, ?int $duration) {
    $this->freezeTime();
    if ($type === 'blind_test') {
        Storage::fake('local');
        Storage::disk('local')->put('audio/test.wav', 'prepared audio');
    }
    $room = openRoom();
    $members = [enterRoom($room, 'Un'), enterRoom($room, 'Deux'), enterRoom($room, 'Trois')];
    $pack = match ($type) {
        'quiz' => quizPack(), 'blind_test' => blindPack(), 'phrase' => phrasePack(), 'drawing' => drawingPack(),
    };
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => $type, 'packs' => [$pack], 'rounds' => in_array($type, ['quiz', 'blind_test'], true) ? 5 : 1, 'duration' => 60, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    $allowed = app(GameBonuses::class)->kinds($type);
    foreach ($game->state['bonuses']['inventory'] as $items) {
        expect($items[0]['kind'])->toBeIn($allowed);
    }
    if ($type === 'drawing') {
        $this->postJson('/rooms/'.$room->code.'/drawing/choose', ['game_id' => $game->id, 'round' => 1, 'choice' => 0])->assertOk();
    }
    giveTestBonus($game, $members[0][0], $kind);
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertOk();
    $effects = $game->fresh()->state['bonuses']['effects'];
    expect(array_column($effects, 'target'))->toBe($type === 'drawing' ? [0] : [$members[1][0], $members[2][0]]);
    foreach ($effects as $effect) {
        expect($effect['kind'])->toBe($kind);
        expect($effect['expiresAt'])->toBe($duration === null ? null : $effect['startedAt'] + $duration);
    }
    $view = $this->getJson('/rooms/'.$room->code.'/state')->assertOk();
    expect($view->json('game.bonuses.inventory'))->toBe([]);
    expect($view->json('game.bonuses.effects'))->toHaveCount($type === 'drawing' ? 1 : 0);
})->with([
    ['quiz', 'bolt', 3], ['quiz', 'dice', 6], ['quiz', 'squatter', 4],
    ['blind_test', 'bolt', 3], ['blind_test', 'dice', 6], ['blind_test', 'squatter', 4], ['blind_test', 'artist', 4],
    ['phrase', 'accent', null], ['phrase', 'sneeze', null], ['drawing', 'stamp', 4], ['drawing', 'paint', 4],
])->group('bonuses');

it('rejects an incompatible or stolen object without changing the pocket or effects', function (string $item) {
    [$room, $members, $game] = bonusQuiz();
    giveTestBonus($game, $members[0][0], 'artist', 'incompatible');
    giveTestBonus($game, $members[1][0], 'bolt', 'stolen');
    $before = $game->fresh()->state['bonuses'];
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => $item])->assertConflict();
    expect($game->fresh()->state['bonuses']['inventory'])->toBe($before['inventory']);
    expect($game->fresh()->state['bonuses']['effects'])->toBe([]);
    expect($game->fresh()->state['bonuses']['used'])->toBe([]);
    expect($game->fresh()->state['bonuses']['launches'] ?? [])->toBe([]);
})->with(['incompatible', 'stolen'])->group('bonuses');

it('does not consume an object when there are no eligible opponents', function () {
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['excluded'] = [$members[1][0]];
    $game->update(['state' => $state]);
    $item = $state['bonuses']['inventory'][$members[0][0]][0];
    $this->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => $item['id']])->assertConflict();
    expect($game->fresh()->state['bonuses']['inventory'][$members[0][0]])->toBe([$item]);
})->group('bonuses');

it('does not let a waiting newcomer or excluded player launch an object', function (bool $newcomer) {
    [$room, $members, $game] = bonusQuiz(true, 3);
    if ($newcomer) {
        [$id, $token] = enterRoom($room, 'Attente');
    } else {
        [$id, $token] = $members[1];
        $state = $game->state;
        $state['excluded'] = [$id];
        $game->update(['state' => $state]);
    }
    giveTestBonus($game, $id, 'bolt');
    $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => 'test-object'])->assertForbidden();
    expect($game->fresh()->state['bonuses']['inventory'][$id])->toHaveCount(1);
})->with([true, false])->group('bonuses');

it('preserves a pending offer across lost rounds and clears it when finished', function () {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['bonuses']['inventory'][$members[1][0]] = [['id' => 'one', 'kind' => 'bolt'], ['id' => 'two', 'kind' => 'dice']];
    $offer = ['id' => 'waiting', 'kind' => 'squatter'];
    $state['bonuses']['pending'][$members[1][0]] = $offer;
    $game->update(['state' => $state]);
    foreach (range(1, 5) as $round) {
        $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
        $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 1))->assertOk();
        $this->travel(3)->seconds();
        $this->postJson('/rooms/'.$room->code.'/screen-presence')->assertOk();
        $this->postJson('/rooms/'.$room->code.'/presence')->assertOk();
        expect($game->fresh()->state['bonuses']['pending'][$members[1][0]] ?? null)->toBe($round === 5 ? null : $offer);
    }
})->group('bonuses');

it('rejects a replacement from a past round without losing the pending offer', function () {
    [$room, $members, $game] = bonusQuiz();
    $state = $game->state;
    $state['bonuses']['pending'][$members[0][0]] = ['id' => 'third', 'kind' => 'dice'];
    $game->update(['state' => $state]);
    $this->postJson('/rooms/'.$room->code.'/bonuses/replace', ['game_id' => $game->id, 'round' => 2, 'item_id' => 'third', 'replace_id' => null])->assertConflict();
    expect($game->fresh()->state['bonuses']['pending'][$members[0][0]]['id'])->toBe('third');
})->group('bonuses');

it('rejects malformed launch requests without consuming the owned object', function (array $invalid) {
    [$room, $members, $game] = bonusQuiz();
    $item = $game->state['bonuses']['inventory'][$members[0][0]][0];
    $values = ['game_id' => $game->id, 'round' => 1, 'item_id' => $item['id']];
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, ...$invalid])->assertUnprocessable();
    expect($game->fresh()->state['bonuses']['inventory'][$members[0][0]])->toBe([$item]);
    expect($game->fresh()->state['bonuses']['effects'])->toBe([]);
})->with([
    [['game_id' => 0]], [['game_id' => []]], [['round' => 0]], [['round' => 'oops']], [['item_id' => null]], [['item_id' => []]], [['item_id' => str_repeat('x', 37)]],
])->group('bonuses');

it('rejects launching during reveal or resuming without consuming the object', function (string $phase) {
    $this->freezeTime();
    [$room, $members, $game] = bonusQuiz();
    $item = $game->state['bonuses']['inventory'][$members[0][0]][0];
    if ($phase === 'reveal') {
        $this->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertOk();
        $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/answer', quizAnswer($room, 0))->assertJsonPath('game.phase', 'reveal');
    } else {
        $this->postJson('/rooms/'.$room->code.'/game/pause')->assertOk();
        $this->postJson('/rooms/'.$room->code.'/game/resume')->assertJsonPath('game.phase', 'resuming');
    }
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/bonuses', ['game_id' => $game->id, 'round' => 1, 'item_id' => $item['id']])->assertConflict();
    expect($game->fresh()->state['bonuses']['inventory'][$members[0][0]])->toBe([$item]);
})->with(['reveal', 'resuming'])->group('bonuses');

it('combines phrase pranks after validation without modifying saved drafts', function () {
    $room = openRoom();
    $members = [enterRoom($room, 'Un'), enterRoom($room, 'Deux'), enterRoom($room, 'Trois')];
    $this->withCookie('plummo_player_'.$room->code, $members[0][1])->postJson('/rooms/'.$room->code.'/games', ['type' => 'phrase', 'packs' => [phrasePack()], 'rounds' => 1, 'duration' => 60, 'bonuses' => true])->assertCreated();
    $game = Game::where('room_id', $room->id)->sole();
    giveTestBonus($game, $members[0][0], 'accent', 'accent');
    giveTestBonus($game, $members[1][0], 'sneeze', 'sneeze');
    $values = ['game_id' => $game->id, 'round' => 1];
    $this->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'accent'])->assertOk();
    $this->withCookie('plummo_player_'.$room->code, $members[1][1])->postJson('/rooms/'.$room->code.'/bonuses', [...$values, 'item_id' => 'sneeze'])->assertOk();
    foreach ($members as [$id, $token]) {
        $this->withCookie('plummo_player_'.$room->code, $token)->postJson('/rooms/'.$room->code.'/phrases/submit', [...$values, 'suffix' => 'Rire avec Robert'])->assertOk()->assertJsonPath('game.me.draft', 'Rire avec Robert');
    }
    $entry = collect($game->fresh()->state['round']['entries'])->firstWhere('author', $members[2][0]);
    expect($entry['text'])->toEndWith('Wiwe avec ATCHOUM ! Wobewt');
    $this->getJson('/rooms/'.$room->code.'/state')->assertJsonMissingPath('game.round.entries.0.author')->assertJsonMissingPath('game.round.drafts');
})->group('bonuses');
