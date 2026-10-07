<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('protects the admin area without registering public accounts', function () {
    $this->withoutVite()->get('/admin')->assertRedirect('/admin/login');
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->get('/register')->assertNotFound();
    $this->post('/admin/register', [])->assertNotFound();
});

it('allows only administrators to authenticate and logout', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($admin);
    $this->withoutVite()->get('/admin')->assertOk();
    $this->get('/admin/user/confirm-password')->assertNotFound();
    $this->post('/admin/user/confirm-password')->assertNotFound();
    $this->get('/admin/user/confirmed-password-status')->assertNotFound();
    $this->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest();
});

it('refuses ordinary accounts and incorrect passwords without identifying an email', function () {
    $ordinary = User::factory()->create();
    $this->postJson('/admin/login', ['email' => $ordinary->email, 'password' => 'password'])->assertUnprocessable();
    $this->assertGuest();
    $admin = User::factory()->create(['is_admin' => true]);
    $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertUnprocessable();
    $this->assertGuest();
});
