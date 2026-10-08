<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $room_id
 * @property string $type
 * @property string $status
 * @property array<string, mixed> $settings
 * @property array{scores: array<int,int>, number: int, phase: string, deadline: float, previous_phase: string, remaining: float, started_at: float, manual_pause: bool, excluded?: list<int>, exhausted?: bool, round: array{content_id: int, payload: array<string,mixed>, participants: list<int>, answers: array<int,array{choice:int,at:float}>, awards: array<int,int>}} $state
 */
class Game extends Model
{
    protected $fillable = ['room_id', 'type', 'status', 'settings', 'state'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'state' => 'array'];
    }
}
