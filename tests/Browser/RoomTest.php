<?php

use App\Models\Room;

it('connects a customized phone to the screen and restores it on refresh', function () {
    $screen = visit('/')->withLocale('en-US')->assertSee('Everyone plays.')->assertNoJavaScriptErrors();
    $room = Room::latest('id')->firstOrFail();
    try {
        $phone = visit('/join')->on()->mobile()->withLocale('en-US');
        $phone->fill('room-code', $room->code)->click('Join')
            ->assertSee('Meet your Plummo.')->fill('player-name', 'Camille')
            ->click('Cap')->click('Enter the room')->assertSee('Camille')
            ->assertSee('You are the room leader.')->assertNoJavaScriptErrors();
        $screen->assertSee('Camille')->assertNoJavaScriptErrors()->screenshot(filename: 'rooms-screen');
        $phone->refresh()->assertSee('Camille')->assertSee('You are the room leader.')->assertNoJavaScriptErrors();
        $phone->screenshot(filename: 'rooms-phone');
        expect($room->players()->count())->toBe(1);
        expect($room->players()->first()->accessories)->toBe(['cap']);
    } finally {
        $room->delete();
    }
});
