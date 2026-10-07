<?php

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
Route::prefix('rooms/{code}')->middleware('throttle:180,1')->group(function (): void {
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
