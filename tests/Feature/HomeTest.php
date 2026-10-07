<?php

use Inertia\Testing\AssertableInertia as Assert;

it('opens without an account with French translations', function () {
    $this->withoutVite()->withHeader('Accept-Language', '')->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->where('locale', 'fr')->where('translations.home.title', 'Tout le monde joue.'));
});
it('uses the supported browser language', function () {
    $this->withoutVite()->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});
it('does not expose player registration', function () {
    $this->get('/register')->assertNotFound();
});
it('responds to the health check', function () {
    $this->get('/up')->assertOk();
});
