<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\DrawingGame;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawingController extends Controller
{
    public function __construct(private RoomService $rooms, private DrawingGame $drawing) {}

    public function action(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['choose', 'guess', 'skip', 'stroke', 'clear', 'undo'], true), 404);

        return $this->rooms->locked($code, function (Room $room) use ($request, $action): JsonResponse {
            $player = $this->rooms->requirePlayer($room, $request);
            $rules = ['game_id' => ['required', 'integer', 'min:1'], 'round' => ['required', 'integer', 'min:1']];
            if ($action === 'choose') {
                $rules['choice'] = ['required', 'integer', 'min:0', 'max:2'];
            }
            if ($action === 'guess') {
                $rules['guess'] = ['required', 'string', 'max:150'];
            }
            if (in_array($action, ['stroke', 'clear', 'undo'], true)) {
                $rules['revision'] = ['required', 'integer', 'min:0'];
            }
            if ($action === 'stroke') {
                $rules += [
                    'id' => ['required', 'integer', 'min:1', 'max:100'],
                    'offset' => ['required', 'integer', 'min:0', 'max:10000'],
                    'color' => ['required', 'in:#35236b,#f05a78,#4a83e8,#30a080,#f5b83d,#ffffff'],
                    'width' => ['required', 'integer', 'min:2', 'max:12'],
                    'points' => ['required', 'array', 'min:1', 'max:200', 'list'],
                    'points.*' => ['required', 'array', 'size:2', 'list'],
                    'points.*.*' => ['required', 'numeric', 'between:0,1'],
                ];
            }
            $values = $request->validate($rules);
            match ($action) {
                'choose' => $this->drawing->choose($room, $player, $values['choice'], $values['game_id'], $values['round']),
                'guess' => $this->drawing->guess($room, $player, $values['guess'], $values['game_id'], $values['round']),
                'skip' => $this->drawing->skip($room, $player, $values['game_id'], $values['round']),
                default => $this->drawing->canvas($room, $player, $action, $values),
            };

            return response()->json($this->rooms->state($room, $player));
        });
    }
}
