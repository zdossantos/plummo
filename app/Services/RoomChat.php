<?php

namespace App\Services;

use App\Events\RoomChanged;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Validation\ValidationException;

class RoomChat
{
    /** @param array<string, mixed>|null $game */
    public function available(?RoomPlayer $player, ?array $game): bool
    {
        if (! $player?->connected()) {
            return false;
        }
        if ($game === null || in_array($game['phase'], ['paused', 'resuming', 'reveal', 'results', 'waiting', 'presenting'], true)) {
            return true;
        }
        $me = $game['me'];
        if ($game['type'] === 'drawing') {
            if ($game['phase'] === 'artist_missing') {
                return ! $me['canGuess'] && (! $me['canSkip'] || $me['votedSkip']);
            }
            if ($player->id === $game['round']['artistId']) {
                return false;
            }

            return $game['phase'] === 'selecting' || ! $me['eligible'] || $me['found'];
        }
        if (! $me['eligible']) {
            return true;
        }
        if ($game['type'] === 'phrase') {
            return match ($game['phase']) {
                'writing' => $me['submitted'],
                'voting' => $me['voted'] || ! array_filter($game['round']['entries'], fn ($entry) => $entry['id'] !== $me['ownEntry']),
                default => false,
            };
        }

        return $me['answered'];
    }

    public function send(Room $room, RoomPlayer $player, string $message, ?int $gameId, ?int $number): void
    {
        $game = app(GameEngine::class)->view($room, $player);
        $player->refresh();
        abort_unless($player->connected(), 403);
        abort_unless(($game['id'] ?? null) === $gameId && ($game['round']['number'] ?? null) === $number, 409);
        abort_unless($this->available($player, $game), 403);
        if ($player->chat_sent_at?->addSeconds(3)->gt(now())) {
            throw ValidationException::withMessages(['message' => __('rooms.chat_cooldown')]);
        }
        $player->update(['chat_message' => $message, 'chat_sent_at' => now()]);
        event(new RoomChanged($room->code));
    }
}
