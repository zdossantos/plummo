<?php

use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\PackController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Rooms\ChatController;
use App\Http\Controllers\Rooms\DrawingController;
use App\Http\Controllers\Rooms\GameController;
use App\Http\Controllers\Rooms\PhraseController;
use App\Http\Controllers\Rooms\PlayerController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\Rooms\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::get('/', [RoomController::class, 'open'])->name('home')->middleware('throttle:30,1');
Route::get('/join', [RoomController::class, 'joinPage'])->name('join');
Route::post('/join', [RoomController::class, 'find'])->middleware('throttle:30,1');
Route::get('/join/{code}', [RoomController::class, 'joinPage'])->name('rooms.join');
Route::get('/screen/{code}', [RoomController::class, 'screen'])->name('rooms.screen');
Route::prefix('rooms/{code}')->middleware('throttle:900,1')->group(function (): void {
    Route::post('screen-presence', [RoomController::class, 'screenPresence']);
    Route::post('broadcast-auth', [RoomController::class, 'broadcastAuth']);
    Route::get('games/{game}/rounds/{round}/audio', [GameController::class, 'audio'])->whereNumber(['game', 'round']);
    Route::post('chat', [ChatController::class, 'store']);
    Route::post('phrases/{action}', [PhraseController::class, 'action']);
    Route::post('drawing/{action}', [DrawingController::class, 'action']);
    Route::post('games', [GameController::class, 'store']);
    Route::post('game-recovery', [GameController::class, 'recover']);
    Route::post('game-options', [GameController::class, 'options']);
    Route::post('answer', [GameController::class, 'answer']);
    Route::post('game/{action}', [GameController::class, 'control']);
    Route::get('qr', [RoomController::class, 'qr'])->name('rooms.qr');
    Route::get('state', [RoomController::class, 'state']);
    Route::post('players', [PlayerController::class, 'store']);
    Route::patch('me', [PlayerController::class, 'update']);
    Route::post('presence', [PlayerController::class, 'presence']);
    Route::post('return', [PlayerController::class, 'returnToRoom']);
    Route::post('leave', [PlayerController::class, 'leave']);
    Route::patch('session', [SessionController::class, 'update']);
    Route::post('chief', [PlayerController::class, 'chief']);
    Route::delete('', [PlayerController::class, 'close']);
});

Route::get('/admin', fn () => Inertia::render('admin/Dashboard'))
    ->middleware(['auth', 'can:administer'])->name('admin.dashboard');

Route::prefix('admin')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->middleware('guest:web')->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['guest:web', 'throttle:admin-login'])->name('login.store');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:administer'])->group(function (): void {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/template/{type}', [ImportController::class, 'template'])->name('imports.template');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::get('imports/{import}/audio/{line}', [ImportController::class, 'audio'])->name('imports.audio');
    Route::get('imports/{import}/errors', [ImportController::class, 'errors'])->name('imports.errors');
    Route::resource('contents', ContentController::class)->except('show');
    Route::get('contents/{content}/audio', [ContentController::class, 'audio'])->name('contents.audio');
    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('packs', PackController::class)->only(['index', 'store', 'update', 'destroy']);
});
