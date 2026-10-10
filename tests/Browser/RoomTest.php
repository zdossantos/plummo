<?php

use App\Models\Room;

it('connects a customized phone to the screen and restores it on refresh', function () {
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.')->assertNoJavaScriptErrors();
    $room = Room::latest('id')->firstOrFail();
    try {
        $phone = visit('/join')->on()->mobile()->withLocale('en-US');
        $phone->fill('room-code', $room->code);
        $phone->click('Join');
        $phone->assertSee('Meet your Plummo.');
        $phone->fill('player-name', 'Camille');
        $phone->click('Continue');
        $phone->click('button[aria-label="Head"]');
        $phone->click('Cap');
        $phone->click('button[aria-label="Close"]');
        $phone->click('Enter the room')->assertSee('Camille')->assertSee('You are the room leader.')->assertNoJavaScriptErrors();
        $screen->assertSee('Camille')->assertNoJavaScriptErrors()->screenshot(filename: 'rooms-screen');
        $phone->refresh()->assertSee('Camille')->assertSee('You are the room leader.')->assertNoJavaScriptErrors();
        $phone->click('button[aria-label="Controls"]')->click('Session')->assertSee('No point limit')->click('label:has-text("No point limit")')->fill('session-target', '500')->click('Save target')->assertSee('Target: 500 points');
        $screen->assertSee('Target: 500 points')->assertSee('Camille')->assertMissing('button')->assertMissing('.page-controls');
        $phone->click('[data-session-extend]')->fill('session-extra', '250')->click('[data-slot="drawer-content"] button[type="submit"]')->assertSee('Target: 250 points')->assertValue('session-target', '250')->assertNoJavaScriptErrors();
        $phone->screenshot(filename: 'rooms-phone');
        expect($room->players()->count())->toBe(1);
        expect($room->players()->first()->accessories)->toBe(['cap']);
    } finally {
        $room->delete();
    }
});
