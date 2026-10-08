<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\GameEngine;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function __construct(private RoomService $rooms) {}

    public function update(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $this->rooms->requireChief($room, $player);
            abort_if(app(GameEngine::class)->active($room) !== null, 409);
            $data = $request->validate([
                'action' => ['required', 'in:configure,extend,restart'],
                'target' => ['present_unless:action,extend', 'nullable', 'integer', 'min:1', 'max:4294967295'],
                'extra' => ['required_if:action,extend', 'integer', 'min:1', 'max:4294967295'],
                'confirm' => ['accepted_if:action,restart'],
            ], [
                'action.*' => __('rooms.invalid_session'), 'target.*' => __('rooms.invalid_points'),
                'extra.*' => __('rooms.invalid_points'), 'confirm.*' => __('rooms.confirm_restart'),
            ]);
            if ($data['action'] === 'extend') {
                $target = (int) $room->players()->max('score') + (int) $data['extra'];
                if ($target > 4294967295) {
                    throw ValidationException::withMessages(['extra' => __('rooms.invalid_points')]);
                }
            } else {
                $target = $data['target'] === null ? null : (int) $data['target'];
            }
            if ($data['action'] === 'restart') {
                $room->players()->update(['score' => 0]);
                $player->refresh();
            }
            $room->update(['point_target' => $target]);

            return response()->json($this->rooms->state($room, $player));
        });
    }
}
