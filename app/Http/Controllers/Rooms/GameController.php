<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Room;
use App\Services\BlindChoices;
use App\Services\BluffGame;
use App\Services\ContentCatalog;
use App\Services\GameEngine;
use App\Services\GameSetup;
use App\Services\MiniGames;
use App\Services\MiniGameScenes;
use App\Services\RoomContentCatalog;
use App\Services\RoomService;
use App\Services\TribunalGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GameController extends Controller
{
    public function __construct(private RoomService $rooms, private GameEngine $games) {}

    public function store(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            $settings = GameSetup::normalize($request->validate(GameSetup::rules($request, true)));
            $this->games->start($room, $settings);

            return response()->json($this->rooms->state($room, $player), 201);
        });
    }

    public function setup(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            abort_if($this->games->active($room) !== null, 409);
            $settings = GameSetup::normalize($request->validate(GameSetup::rules($request, false)));
            app(GameSetup::class)->configure($room, $settings);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function ready(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            abort_unless($player->connected(), 403);
            abort_if($this->games->active($room) !== null || $room->game_setup === null, 409);
            $values = $request->validate(['ready' => ['required', 'boolean'], 'revision' => ['required', 'integer', 'min:0']]);
            abort_unless($values['revision'] === $room->setup_revision, 409);
            $player->update(['ready_revision' => $values['ready'] ? $room->setup_revision : null]);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function options(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $this->rooms->requireChief($room, $this->rooms->requirePlayer($room, $request));
            $packs = $request->validate(['type' => ['sometimes', 'in:quiz,blind_test,drawing,phrase,mini_games,tribunal,bluff'], 'packs' => ['sometimes', 'array', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id']]);
            $connected = $room->players()->get()->filter(fn ($player) => $player->connected())->count();
            $games = array_values(array_filter(['quiz', 'blind_test', 'drawing', 'phrase', 'mini_games', 'tribunal', 'bluff'], fn ($game) => $connected >= match ($game) {
                'drawing' => 2, 'phrase', 'tribunal', 'bluff' => 3, default => 1
            }));
            if (($packs['type'] ?? '') === 'mini_games') {
                return response()->json(['games' => $games, 'connectedPlayers' => $connected, 'packs' => [], 'trials' => app(MiniGameScenes::class)->available($connected), 'availability' => null, 'distinctSongs' => null]);
            }
            $type = ContentType::from($packs['type'] ?? 'quiz');
            $packs = $packs['packs'] ?? [];

            $available = Pack::orderBy('name')->get(['id', 'name'])->filter(fn ($pack) => app(ContentCatalog::class)->query($type, [$pack->id])->exists())->values();

            return response()->json(['games' => $games, 'connectedPlayers' => $connected, 'packs' => $available, 'distinctSongs' => $type === ContentType::BlindTest ? app(BlindChoices::class)->count() : null, 'availability' => app(RoomContentCatalog::class)->availability($room, $type, $packs)]);
        });
    }

    public function recover(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            $settings = $request->validate(['packs' => ['present', 'array', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id'], 'allow_repeats' => ['required', 'boolean']]);
            $this->games->recover($room, $settings);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function answer(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $values = $request->validate(['choice' => ['required', 'integer', 'min:0', 'max:'.(match ($this->games->active($room)?->type) {
                'blind_test' => 7, 'mini_games' => 11, default => 3
            })], 'game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1']]);
            $this->games->answer($room, $player, $values['choice'], $values['game_id'], $values['round']);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function bluff(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['write', 'vote'], true), 404);

        return $this->rooms->locked($code, function (Room $room) use ($request, $action): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'], 'text' => ['nullable', 'string', 'max:120'], 'submit' => [$action === 'write' ? 'required' : 'nullable', 'boolean'], 'choice' => [$action === 'vote' ? 'required' : 'nullable', 'uuid']]);
            if ($action === 'write') {
                app(BluffGame::class)->write($room, $player, $values['text'] ?? '', $values['submit'], $values['game_id'], $values['round']);
            } else {
                app(BluffGame::class)->vote($room, $player, $values['choice'], $values['game_id'], $values['round']);
            }

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function tribunal(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['finish', 'vote'], true), 404);

        return $this->rooms->locked($code, function (Room $room) use ($request, $action): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'], 'choice' => [$action === 'vote' ? 'required' : 'nullable', 'integer', 'min:0', 'max:1']]);
            app(TribunalGame::class)->action($room, $player, $values['game_id'], $values['round'], $action, $values['choice'] ?? null);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function miniWrite(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'], 'text' => ['nullable', 'string', 'max:80'], 'submit' => ['required', 'boolean']]);
            app(MiniGames::class)->write($room, $player, $values['game_id'], $values['round'], $values['text'] ?? '', $values['submit']);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function audio(Request $request, string $code, int $game, int $round): BinaryFileResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request, $game, $round): BinaryFileResponse {
            abort_unless($request->session()->get('plummo.screen') === $room->id, 403);
            $current = $this->games->tick($room);
            abort_unless($current !== null && $current->id === $game && $current->type === 'blind_test' && $current->state['number'] === $round, 404);
            $path = $current->state['round']['payload']['audio_path'];
            abort_unless(Storage::disk('local')->exists($path), 404);

            return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
        });
    }

    public function control(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['pause', 'resume', 'stop', 'lobby'], true), 404);

        return $this->rooms->locked($code, function (Room $room) use ($request, $action): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            $this->games->control($room, $action);

            return response()->json($this->rooms->state($room, $player));
        });
    }
}
