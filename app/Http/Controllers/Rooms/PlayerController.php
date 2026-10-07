<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerAppearanceRequest;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PlayerController extends Controller
{
    public function __construct(private RoomService $rooms) {}

    public function store(PlayerAppearanceRequest $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->join($room, $request, $request->validated());

            return response()->json($this->rooms->state($room, $player), 201);
        });
    }

    public function update(PlayerAppearanceRequest $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $player->update($request->validated());

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function presence(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            if ($player->left_at === null) {
                $this->rooms->returnPlayer($room, $player);
            }

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function returnToRoom(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->returnPlayer($room, $this->rooms->requirePlayer($room, $request));

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function leave(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $player->update(['left_at' => now(), 'waiting' => false]);
            $this->rooms->refreshPresence($room);

            return response()->json($this->rooms->state($room, $player));
        });
    }

    public function chief(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            $this->rooms->requireChief($room, $this->rooms->requirePlayer($room, $request));
            $request->validate(['playerId' => ['required', 'integer']], ['playerId.*' => __('rooms.invalid_target')]);
            $target = $room->players()->find($request->integer('playerId'));
            if ($target === null || ! $target->connected()) {
                throw ValidationException::withMessages(['playerId' => __('rooms.invalid_target')]);
            }
            $room->update(['owner_id' => $target->id]);

            return response()->json($this->rooms->state($room, $this->rooms->recognized($room, $request)));
        });
    }

    public function close(Request $request, string $code): Response
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): Response {
            $this->rooms->requireChief($room, $this->rooms->requirePlayer($room, $request));
            $request->validate(['confirm' => ['required', 'accepted']], ['confirm.*' => __('rooms.confirm_close')]);
            $room->delete();

            return response()->noContent();
        });
    }
}
