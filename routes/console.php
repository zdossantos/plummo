<?php

use App\Services\ContentImports;
use App\Services\RoomService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rooms:prune', function (RoomService $rooms) {
    $this->info('Expired rooms removed: '.$rooms->prune());
})->purpose('Remove rooms empty for thirty minutes');

Schedule::command('rooms:prune')->everyMinute()->withoutOverlapping();

Artisan::command('imports:prune', function (ContentImports $imports) {
    $this->info('Expired imports removed: '.$imports->prune());
})->purpose('Remove expired import previews and temporary files');
Schedule::command('imports:prune')->everyMinute()->withoutOverlapping();
