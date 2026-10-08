<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Room;
use App\Services\BlindChoices;
use App\Services\GameEngine;
use App\Services\RoomContentCatalog;
use App\Services\RoomService;
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
            $settings = $request->validate(['type' => ['required', 'in:quiz,blind_test,drawing'], 'packs' => ['required', 'array', 'min:1', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id'], 'rounds' => ['required', 'integer', $request->input('type') === 'drawing' ? 'min:1' : 'min:5', $request->input('type') === 'drawing' ? 'max:5' : 'max:30'], 'duration' => ['required', 'integer', $request->input('type') === 'drawing' ? 'min:30' : 'min:10', 'max:150'], 'allow_repeats' => ['sometimes', 'boolean']]);
            $this->games->start($room, $settings);

            return response()->json($this->rooms->state($room, $player), 201);
        });
    }

    public function options(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $this->rooms->requireChief($room, $this->rooms->requirePlayer($room, $request));
            $packs = $request->validate(['type' => ['sometimes', 'in:quiz,blind_test,drawing'], 'packs' => ['sometimes', 'array', 'min:1', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id']]);
            $type = ContentType::from($packs['type'] ?? 'quiz');
            $packs = $packs['packs'] ?? [];

            return response()->json(['connectedPlayers' => $room->players()->get()->filter(fn ($player) => $player->connected())->count(), 'packs' => Pack::orderBy('name')->get(['id', 'name']), 'distinctSongs' => $type === ContentType::BlindTest ? app(BlindChoices::class)->count() : null, 'availability' => $packs === [] ? null : app(RoomContentCatalog::class)->availability($room, $type, $packs)]);
        });
    }

    public function recover(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            $settings = $request->validate(['packs' => ['required', 'array', 'min:1', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id'], 'allow_repeats' => ['required', 'boolean']]);
            $this->games->recover($room, $settings);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function answer(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $values = $request->validate(['choice' => ['required', 'integer', 'min:0', 'max:'.($this->games->active($room)?->type === 'blind_test' ? 7 : 3)], 'game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1']]);
            $this->games->answer($room, $player, $values['choice'], $values['game_id'], $values['round']);

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
