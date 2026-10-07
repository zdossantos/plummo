<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $disconnected_at
 * @property CarbonImmutable|null $left_at
 * @property list<string> $accessories
 */
class RoomPlayer extends Model
{
    protected $fillable = ['room_id', 'identity_hash', 'name', 'color', 'accessories', 'score', 'last_seen_at', 'connected_since', 'disconnected_at', 'left_at', 'waiting'];

    protected $hidden = ['identity_hash'];

    protected function casts(): array
    {
        return [
            'accessories' => 'array',
            'last_seen_at' => 'immutable_datetime',
            'connected_since' => 'immutable_datetime',
            'disconnected_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
            'waiting' => 'boolean',
            'score' => 'integer',
        ];
    }

    public function connected(): bool
    {
        return $this->left_at === null && ! $this->waiting && $this->disconnected_at === null;
    }

    public function occupiesPlace(): bool
    {
        return $this->left_at === null && ! $this->waiting &&
            ($this->disconnected_at === null || $this->disconnected_at->addMinutes(2)->isFuture());
    }

    public function status(): string
    {
        return match (true) {
            $this->left_at !== null => 'left',
            $this->waiting => 'waiting',
            $this->disconnected_at !== null => 'disconnected',
            default => 'connected',
        };
    }

    /** @return array{id: int, name: string, color: string, accessories: list<string>, score: int, status: string} */
    public function publicData(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'color' => $this->color,
            'accessories' => $this->accessories, 'score' => $this->score, 'status' => $this->status()];
    }
}
