<?php

use App\Models\Game;
use App\Models\Room;
use App\Services\ContentImports;
use App\Services\GameEngine;
use App\Services\RoomService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

Artisan::command('rooms:prune', function (RoomService $rooms) {
    $this->info('Expired rooms removed: '.$rooms->prune());
})->purpose('Remove rooms empty for thirty minutes');

Schedule::command('rooms:prune')->everyMinute()->withoutOverlapping();

Artisan::command('imports:prune', function (ContentImports $imports) {
    $this->info('Expired imports removed: '.$imports->prune());
})->purpose('Remove expired import previews and temporary files');
Schedule::command('imports:prune')->everyMinute()->withoutOverlapping();

Artisan::command('games:tick', function (RoomService $rooms, GameEngine $games) {
    foreach (Game::where('status', 'active')->pluck('room_id') as $id) {
        $code = Room::whereKey($id)->value('code');
        if ($code !== null) {
            try {
                $rooms->locked($code, fn ($room) => $games->tick($room));
            } catch (NotFoundHttpException) {
                // A concurrent closure may already have removed this room.
            }
        }
    }
})->purpose('Advance game deadlines and connection pauses');
Schedule::command('games:tick')->everySecond()->withoutOverlapping();
