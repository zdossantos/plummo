<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\PlummoCatalog;
use App\Services\RoomService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RoomController extends Controller
{
    public function __construct(private RoomService $rooms, private PlummoCatalog $catalog) {}

    public function open(Request $request): RedirectResponse
    {
        $room = Room::whereKey($request->session()->get('plummo.screen'))->first();
        if ($room !== null) {
            $room = DB::transaction(function () use ($room): ?Room {
                $room = Room::whereKey($room->id)->lockForUpdate()->first();
                if ($room !== null) {
                    $this->rooms->refreshPresence($room);
                    if ($room->empty_since !== null && $room->empty_since->addMinutes(30)->lte(now())) {
                        $room->delete();

                        return null;
                    }
                }

                return $room;
            });
        }
        if ($room === null) {
            $room = $this->rooms->create();
            $request->session()->put('plummo.screen', $room->id);
        }

        $room->update(['screen_seen_at' => now()]);

        return redirect()->route('rooms.screen', $room->code);
    }

    public function screen(Request $request, string $code): InertiaResponse
    {
        return $this->rooms->locked($code, fn (Room $room): InertiaResponse => Inertia::render('rooms/Screen', [
            ...$this->rooms->state($room, screen: $request->session()->get('plummo.screen') === $room->id), 'joinUrl' => rtrim(config('app.url'), '/').route('rooms.join', $room->code, false), 'manualUrl' => rtrim(config('app.url'), '/').route('join', absolute: false),
        ]));
    }

    public function joinPage(Request $request, ?string $code = null): InertiaResponse
    {
        if ($code === null) {
            return Inertia::render('rooms/Join', ['code' => null, 'me' => null, 'catalog' => $this->catalog->data()]);
        }

        return $this->rooms->locked($code, fn (Room $room): InertiaResponse => Inertia::render('rooms/Join', [
            'code' => $code, 'me' => $this->rooms->recognized($room, $request)?->publicData(), 'catalog' => $this->catalog->data(),
        ]));
    }

    public function find(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        if (is_string($code)) {
            $request->merge(['code' => strtoupper(trim($code))]);
        }
        $request->validate(['code' => ['required', 'string', 'size:6', 'exists:rooms,code']], ['code.*' => __('rooms.unknown')]);

        return redirect()->route('rooms.join', $request->input('code'));
    }

    public function state(string $code): JsonResponse
    {
        return $this->rooms->locked($code, fn (Room $room): JsonResponse => response()->json($this->rooms->state($room)));
    }

    public function screenPresence(Request $request, string $code): JsonResponse
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): JsonResponse {
            abort_unless($request->session()->get('plummo.screen') === $room->id, 403);
            $request->validate(['audio_ready' => ['sometimes', 'boolean']]);
            $this->rooms->reportAudio($room, $request->boolean('audio_ready'));
            $room->update(['screen_seen_at' => now()]);

            return response()->json($this->rooms->state($room, screen: true));
        });
    }

    public function broadcastAuth(Request $request, string $code): mixed
    {
        return $this->rooms->locked($code, function (Room $room) use ($request): mixed {
            $player = $this->rooms->recognized($room, $request);
            abort_unless($request->session()->get('plummo.screen') === $room->id || $player?->connected(), 403);
            abort_unless($request->input('channel_name') === 'private-room.'.$room->code, 403);
            $request->validate(['socket_id' => ['required', 'regex:/^\d+\.\d+$/']]);
            $request->setUserResolver(fn () => $player ?? (object) ['id' => 'screen-'.$room->id]);
            Broadcast::channel('room.'.$room->code, fn () => true);

            return Broadcast::auth($request);
        });
    }

    public function qr(string $code): Response
    {
        return $this->rooms->locked($code, function (Room $room): Response {
            $writer = new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd));

            return response($writer->writeString(rtrim(config('app.url'), '/').route('rooms.join', $room->code, false)), 200, ['Content-Type' => 'image/svg+xml']);
        });
    }
}
