<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Gate::define('administer', fn (User $user): bool => (bool) $user->is_admin);
        Fortify::loginView(fn () => Inertia::render('admin/Login'));
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', $request->input('email'))->first();

            return $user?->is_admin && Hash::check($request->input('password'), $user->password) ? $user : null;
        });
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = $request->input('email');
            $key = is_string($email) ? mb_strtolower($email) : '';

            return Limit::perMinute(5)->by(hash('sha256', $key.'|'.$request->ip()));
        });
    }
}
