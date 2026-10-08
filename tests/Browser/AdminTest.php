<?php

use App\Models\User;

it('signs in and out of the protected administration', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    try {
        visit('/admin/login')->withLocale('en-US')
            ->fill('admin-email', $admin->email)->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->fill('admin-password', 'password')
            ->click('Sign in')->assertSee('Welcome to Plummo administration.')
            ->assertNoJavaScriptErrors()->screenshot(filename: 'admin-dashboard')
            ->click('Sign out')->assertSee('Administrator sign in')->assertNoJavaScriptErrors();
    } finally {
        $admin->delete();
    }
});
