<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GameSetup
{
    /** @return array<string, list<string>> */
    public static function rules(Request $request, bool $launch): array
    {
        $type = $request->input('type');
        $tours = in_array($type, ['drawing', 'phrase', 'tribunal'], true);

        return [
            'type' => ['required', 'in:quiz,blind_test,drawing,phrase,mini_games,tribunal,bluff'],
            'packs' => ['exclude_if:type,mini_games', 'present', 'array', 'max:3'],
            'packs.*' => ['integer', 'distinct', 'exists:packs,id'],
            'rounds' => ['required', 'integer', $tours ? 'min:1' : 'min:5', $tours ? 'max:5' : 'max:30'],
            'duration' => ['required', 'integer', in_array($type, ['drawing', 'phrase', 'bluff'], true) ? 'min:30' : 'min:10', in_array($type, ['mini_games', 'tribunal'], true) ? 'max:60' : 'max:150'],
            'trials' => ['exclude_unless:type,mini_games', $launch ? 'required_if:type,mini_games' : 'present', 'array', $launch ? 'min:1' : 'min:0', 'max:5'],
            'trials.*' => ['string', 'distinct', 'in:'.implode(',', MiniGameScenes::TRIALS)],
            'allow_repeats' => ['sometimes', 'boolean'],
            'bonuses' => ['sometimes', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function normalize(array $settings): array
    {
        $selection = $settings['type'] === 'mini_games' ? 'trials' : 'packs';
        $items = $settings[$selection] ?? [];
        if ($selection === 'packs') {
            $items = array_map(intval(...), $items);
        }
        sort($items);

        return ['type' => $settings['type'], $selection => $items, 'rounds' => (int) $settings['rounds'], 'duration' => (int) $settings['duration'], 'allow_repeats' => $selection === 'packs' && (bool) ($settings['allow_repeats'] ?? false), 'bonuses' => (bool) ($settings['bonuses'] ?? false)];
    }

    /** @param array<string, mixed> $settings */
    public function configure(Room $room, array $settings): void
    {
        if ($room->game_setup != $settings) {
            $this->reset($room);
            $room->update(['game_setup' => $settings]);
        }
    }

    public function reset(Room $room): void
    {
        $room->players()->update(['ready_revision' => null]);
        $room->update(['setup_revision' => $room->setup_revision + 1]);
    }

    /** @param array<string, mixed> $settings */
    public function requireReady(Room $room, array $settings): void
    {
        $players = $room->players()->get()->filter(fn (RoomPlayer $player) => $player->connected());
        $chiefId = app(RoomService::class)->chiefId($room);
        if ($room->game_setup != self::normalize($settings) || $players->isEmpty() || $players->contains(fn (RoomPlayer $player) => $player->id !== $chiefId && $player->ready_revision !== $room->setup_revision)) {
            throw ValidationException::withMessages(['ready' => __('rooms.ready_required')]);
        }
    }
}
