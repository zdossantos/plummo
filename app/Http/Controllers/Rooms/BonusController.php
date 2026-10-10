<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\GameBonuses;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function store(Request $request, string $code): JsonResponse
    {
        $rooms = app(RoomService::class);

        return $rooms->locked($code, function (Room $room) use ($request, $rooms): JsonResponse {
            $player = $rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'], 'item_id' => ['required', 'string', 'max:36']]);
            app(GameBonuses::class)->use($room, $player, $values['game_id'], $values['round'], $values['item_id']);

            return response()->json($rooms->state($room, $player));
        });
    }

    public function replace(Request $request, string $code): JsonResponse
    {
        $rooms = app(RoomService::class);

        return $rooms->locked($code, function (Room $room) use ($request, $rooms): JsonResponse {
            $player = $rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'], 'item_id' => ['required', 'string', 'max:36'], 'replace_id' => ['present', 'nullable', 'string', 'max:36']]);
            app(GameBonuses::class)->replace($room, $player, $values['game_id'], $values['round'], $values['item_id'], $values['replace_id']);

            return response()->json($rooms->state($room, $player));
        });
    }
}
