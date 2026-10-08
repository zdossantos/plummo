<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\PhraseGame;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhraseController extends Controller
{
    public function action(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['draft', 'submit', 'vote'], true), 404);

        return app(RoomService::class)->locked($code, function (Room $room) use ($request, $action): JsonResponse {
            $rooms = app(RoomService::class);
            $player = $rooms->requirePlayer($room, $request);
            $values = $request->validate(['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1'],
                ...($action === 'vote' ? ['choice' => ['required', 'string', 'max:36']] : ['suffix' => ['present', 'nullable', 'string', 'max:150']])]);
            if ($action === 'vote') {
                app(PhraseGame::class)->vote($room, $player, $values['choice'], $values['game_id'], $values['round']);
            } else {
                app(PhraseGame::class)->write($room, $player, $values['suffix'] ?? '', $action === 'submit', $values['game_id'], $values['round']);
            }

            return response()->json($rooms->state($room, $player));
        });
    }
}
