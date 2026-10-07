<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomContentCatalog
{
    public function __construct(private ContentCatalog $catalog) {}

    /** @param list<int> $packIds
     * @return array{total: int, unseen: int}
     */
    public function availability(Room $room, ContentType $type, array $packIds): array
    {
        $query = $this->catalog->query($type, $packIds);

        return ['total' => (clone $query)->count(), 'unseen' => $query->whereNotIn('contents.id', $this->history($room))->count()];
    }

    /** Select and record exactly the contents that are being revealed by a game.
     * @param  list<int>  $packIds
     * @return Collection<int, Content>
     */
    public function reveal(Room $room, ContentType $type, array $packIds, int $count, bool $allowRepeats = false): Collection
    {
        if ($count < 1 || $count > 30) {
            throw ValidationException::withMessages(['count' => __('rooms.invalid_content_count')]);
        }

        return DB::transaction(function () use ($room, $type, $packIds, $count, $allowRepeats): Collection {
            $locked = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $query = $this->catalog->query($type, $packIds);
            if (! $allowRepeats) {
                $query->whereNotIn('contents.id', $this->history($locked));
            } else {
                $query->orderByRaw('contents.id IN (SELECT content_id FROM room_content_history WHERE room_id = ?)', [$locked->id]);
            }
            $contents = $query->inRandomOrder()->limit($count)->get();
            if ($contents->count() !== $count) {
                throw ValidationException::withMessages(['packs' => __('rooms.contents_exhausted')]);
            }
            foreach ($contents as $content) {
                DB::table('room_content_history')->insertOrIgnore(['room_id' => $locked->id, 'content_id' => $content->id, 'revealed_at' => now()]);
            }

            return $contents;
        }, 3);
    }

    private function history(Room $room): Builder
    {
        return DB::table('room_content_history')->where('room_id', $room->id)->select('content_id');
    }
}
