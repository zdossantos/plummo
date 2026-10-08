<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $room_id
 * @property string $type
 * @property string $status
 * @property array<string, mixed> $settings
 * @property array<string,mixed> $state
 */
class Game extends Model
{
    protected $fillable = ['room_id', 'type', 'status', 'settings', 'state'];

    /** @return array<int,array{at:float,choice?:int}> */
    public function answers(): array
    {
        return $this->state['round']['answers'];
    }

    protected function casts(): array
    {
        return ['settings' => 'array', 'state' => 'array'];
    }
}
