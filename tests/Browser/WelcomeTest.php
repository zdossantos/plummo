<?php

it('loads Plummo and persists the chosen appearance', function () {
    visit('/join')->assertSee('plummo')->assertNoJavaScriptErrors()
        ->click('Dark')->assertAttribute(':root', 'class', 'dark')
        ->refresh()->assertAttribute(':root', 'class', 'dark');
});
