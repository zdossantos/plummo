<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonImmutable|null $empty_since */
class Room extends Model
{
    protected $fillable = ['code', 'owner_id', 'empty_since'];

    protected function casts(): array
    {
        return ['empty_since' => 'immutable_datetime'];
    }

    /** @return HasMany<RoomPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }
}
