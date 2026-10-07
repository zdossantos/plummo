<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

it('creates an administrator through interactive credentials only', function () {
    $this->artisan('admin:create')
        ->expectsQuestion('Nom', 'Camille')
        ->expectsQuestion('Adresse e-mail', 'admin@example.test')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'a-long-test-password')
        ->expectsQuestion('Confirmer le mot de passe', 'a-long-test-password')
        ->assertSuccessful();
    $admin = User::where('email', 'admin@example.test')->firstOrFail();
    expect($admin->is_admin)->toBeTrue();
    expect(Hash::check('a-long-test-password', $admin->password))->toBeTrue();
});

it('rejects duplicates and mismatched passwords without changing existing accounts', function () {
    $user = User::factory()->create(['email' => 'existing@example.test']);
    $this->artisan('admin:create')->expectsQuestion('Nom', 'Autre')
        ->expectsQuestion('Adresse e-mail', $user->email)
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'a-long-test-password')
        ->expectsQuestion('Confirmer le mot de passe', 'different-password')
        ->assertFailed();
    expect($user->fresh()->name)->toBe($user->name);
    expect(User::where('email', $user->email)->count())->toBe(1);
    $this->artisan('admin:create')->expectsQuestion('Nom', 'Autre')
        ->expectsQuestion('Adresse e-mail', 'new@example.test')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'short')
        ->expectsQuestion('Confirmer le mot de passe', 'short')
        ->assertFailed();
    $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
});
