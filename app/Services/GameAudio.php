<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Game;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GameAudio
{
    public function copy(Game $game, Content $song): string
    {
        $source = $song->payload['audio_path'] ?? '';
        $path = 'games/'.$game->room_id.'/'.$game->id.'/'.($game->state['number'] + 1).'.'.pathinfo($source, PATHINFO_EXTENSION);
        if ($source === '' || ! Storage::disk('local')->exists($source) || ! Storage::disk('local')->copy($source, $path)) {
            throw ValidationException::withMessages(['packs' => __('rooms.blind_audio_missing')]);
        }

        return $path;
    }
}
