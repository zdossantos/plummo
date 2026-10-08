<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property CarbonImmutable|null $empty_since
 * @property CarbonImmutable|null $screen_seen_at
 */
class Room extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (Room $room): void {
            DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory('games/'.$room->id));
        });
    }

    protected $fillable = ['code', 'owner_id', 'empty_since', 'point_target', 'screen_seen_at'];

    protected function casts(): array
    {
        return ['empty_since' => 'immutable_datetime', 'screen_seen_at' => 'immutable_datetime', 'point_target' => 'integer'];
    }

    /** @return HasMany<RoomPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }
}
