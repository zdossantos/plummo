<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomChat;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function store(Request $request, string $code): JsonResponse
    {
        return app(RoomService::class)->locked($code, function (Room $room) use ($request): JsonResponse {
            $rooms = app(RoomService::class);
            $player = $rooms->requirePlayer($room, $request);
            $values = $request->validate(['message' => ['required', 'string', 'max:80'],
                'game_id' => ['present', 'nullable', 'integer', 'min:1'], 'round' => ['present', 'nullable', 'integer', 'min:1']]);
            app(RoomChat::class)->send($room, $player, $values['message'], $values['game_id'], $values['round']);

            return response()->json($rooms->state($room, $player));
        });
    }
}
