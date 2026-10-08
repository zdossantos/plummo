<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomPlayer;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function create(): Room
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            try {
                return Room::create(['code' => $code, 'empty_since' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Retry a collision; the unique index arbitrates concurrent creations.
            }
        }
        abort(503);
    }

    /** @param Closure(Room): mixed $action */
    public function locked(string $code, Closure $action): mixed
    {
        $result = DB::transaction(function () use ($code, $action): mixed {
            $room = Room::where('code', $code)->lockForUpdate()->first();
            if ($room === null) {
                return null;
            }
            $this->refreshPresence($room);
            if ($room->empty_since !== null && $room->empty_since->addMinutes(30)->lte(now())) {
                $room->delete();

                return null;
            }

            return $action($room);
        }, 3);
        // Outside the transaction so deleting an expired room is committed even on a 404.
        abort_if($result === null, 404);

        return $result;
    }

    public function refreshPresence(Room $room): void
    {
        $players = $room->players()->orderBy('id')->get();
        foreach ($players as $player) {
            if ($player->left_at === null && $player->disconnected_at === null && $player->last_seen_at->addSeconds(15)->lte(now())) {
                app(GameEngine::class)->withdraw($room, $player);
                $player->update(['disconnected_at' => $player->last_seen_at->addSeconds(15)]);
            }
        }
        if ($players->contains(fn (RoomPlayer $player): bool => $player->connected())) {
            $room->update(['empty_since' => null]);
        } elseif ($room->empty_since === null) {
            $last = $players->map(fn (RoomPlayer $player) => $player->left_at ?? $player->disconnected_at)->filter()->sort()->last();
            $room->update(['empty_since' => $last ?? now()]);
        }
    }

    public function prune(): int
    {
        $deleted = 0;
        foreach (Room::select('id')->lazyById() as $candidate) {
            $deleted += DB::transaction(function () use ($candidate): int {
                $room = Room::whereKey($candidate->id)->lockForUpdate()->first();
                if ($room === null) {
                    return 0;
                }
                $this->refreshPresence($room);
                if ($room->empty_since !== null && $room->empty_since->addMinutes(30)->lte(now())) {
                    $room->delete();

                    return 1;
                }

                return 0;
            }, 3);
        }

        return $deleted;
    }

    public function recognized(Room $room, Request $request): ?RoomPlayer
    {
        $token = $request->cookie('plummo_player_'.$room->code);
        if (! is_string($token) || $token === '') {
            return null;
        }

        return $room->players()->where('identity_hash', hash('sha256', $token))->first();
    }

    public function requirePlayer(Room $room, Request $request): RoomPlayer
    {
        $player = $this->recognized($room, $request);
        abort_if($player === null, 403);

        return $player;
    }

    /** @param array<string, mixed> $appearance */
    public function join(Room $room, Request $request, array $appearance): RoomPlayer
    {
        $existing = $this->recognized($room, $request);
        if ($existing !== null) {
            return $this->returnPlayer($room, $existing);
        }
        if ($this->occupied($room) >= 8) {
            throw ValidationException::withMessages(['room' => __('rooms.full')]);
        }
        $token = Str::random(64);
        $player = $room->players()->create([
            ...$appearance, 'identity_hash' => hash('sha256', $token), 'last_seen_at' => now(), 'connected_since' => now(),
        ]);
        Cookie::queue(cookie('plummo_player_'.$room->code, $token, 43200, '/', null, config('session.secure'), true, false, 'lax'));
        if ($room->owner_id === null) {
            $room->update(['owner_id' => $player->id]);
        }
        $room->update(['empty_since' => null]);

        return $player;
    }

    public function occupied(Room $room): int
    {
        return $room->players()->get()->filter(fn (RoomPlayer $player): bool => $player->occupiesPlace())->count();
    }

    public function chiefId(Room $room): ?int
    {
        $connected = $room->players()->orderBy('connected_since')->orderBy('id')->get()->filter(fn (RoomPlayer $player): bool => $player->connected());

        if ($room->owner_id !== null && $connected->contains('id', $room->owner_id)) {
            return $room->owner_id;
        }

        return $connected->first()?->id;
    }

    public function requireChief(Room $room, RoomPlayer $player): void
    {
        abort_unless($player->connected() && $this->chiefId($room) === $player->id, 403);
    }

    public function returnPlayer(Room $room, RoomPlayer $player): RoomPlayer
    {
        if (! $player->connected()) {
            app(GameEngine::class)->withdraw($room, $player);
        }
        if (! $player->occupiesPlace() && $this->occupied($room) >= 8) {
            $player->update(['left_at' => null, 'waiting' => true, 'last_seen_at' => now(), 'disconnected_at' => null]);
        } else {
            if (! $player->connected()) {
                $player->connected_since = now();
            }
            $player->update(['left_at' => null, 'waiting' => false, 'last_seen_at' => now(), 'disconnected_at' => null]);
            $room->update(['empty_since' => null]);
        }

        return $player;
    }

    /** @return list<array<string, mixed>> */
    public function ranking(Room $room): array
    {
        $rank = 0;
        $previous = null;
        $result = [];
        foreach ($room->players()->orderByDesc('score')->orderBy('id')->get() as $index => $player) {
            if ($player->score !== $previous) {
                $rank = $index + 1;
                $previous = $player->score;
            }
            $result[] = [...$player->publicData(), 'rank' => $rank];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function state(Room $room, ?RoomPlayer $me = null, bool $screen = false): array
    {
        $game = app(GameEngine::class)->view($room, $me, $screen);
        $me?->refresh();

        return [
            'serverTime' => app(GameEngine::class)->time(),
            'canChat' => app(RoomChat::class)->available($me, $game),
            'game' => $game,
            'room' => [
                'code' => $room->code, 'capacity' => 8, 'occupied' => $this->occupied($room), 'chiefId' => $this->chiefId($room),
                'pointTarget' => $room->point_target, 'ranking' => $this->ranking($room),
                'players' => $room->players()->orderBy('id')->get()->filter(fn (RoomPlayer $player): bool => $player->occupiesPlace())->map(fn (RoomPlayer $player): array => $player->publicData())->values()->all(),
            ],
            'me' => $me?->publicData(),
        ];
    }
}
