<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Room;
use App\Services\GameEngine;
use App\Services\RoomContentCatalog;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private RoomService $rooms, private GameEngine $games) {}

    public function store(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            $settings = $request->validate(['type' => ['required', 'in:quiz'], 'packs' => ['required', 'array', 'min:1', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id'], 'rounds' => ['required', 'integer', 'min:5', 'max:30'], 'duration' => ['required', 'integer', 'min:10', 'max:150'], 'allow_repeats' => ['sometimes', 'boolean']]);
            $this->games->start($room, $settings);

            return response()->json($this->rooms->state($room, $player), 201);
        });
    }

    public function options(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $this->rooms->requireChief($room, $this->rooms->requirePlayer($room, $request));
            $packs = $request->validate(['packs' => ['sometimes', 'array', 'min:1', 'max:3'], 'packs.*' => ['integer', 'distinct', 'exists:packs,id']])['packs'] ?? [];

            return response()->json(['packs' => Pack::orderBy('name')->get(['id', 'name']), 'availability' => $packs === [] ? null : app(RoomContentCatalog::class)->availability($room, ContentType::Quiz, $packs)]);
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
            $values = $request->validate(['choice' => ['required', 'integer', 'min:0', 'max:3'], 'game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1']]);
            $this->games->answer($room, $player, $values['choice'], $values['game_id'], $values['round']);

            return response()->json($this->rooms->state($room, $player));
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
