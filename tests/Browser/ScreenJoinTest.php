<?php

it('keeps a large scannable invitation inside the shared screen without covering the players', function (int $width, int $height) {
    $room = openRoom();
    test()->withVite();
    try {
        $page = visit('/screen/'.$room->code, ['viewport' => compact('width', 'height')])->withLocale('en-US');
        $page->assertVisible('[data-testid="screen-join"] img')->assertSee('Everyone plays.')->assertPresent('[data-testid="screen-join"] img')->assertMissing('.page-controls');
        expect($page->page()->evaluate('() => { const image = document.querySelector("[data-testid=screen-join] img"); return image.complete && image.naturalWidth > 0; }'))->toBeTrue();
        expect($page->page()->evaluate('() => { const r = document.querySelector("[data-testid=screen-join]").getBoundingClientRect(); const footer = document.querySelector(".viewport-footer").getBoundingClientRect(); return r.top >= 0 && r.right <= innerWidth && r.bottom <= footer.top && r.left >= 0; }'))->toBeTrue();
        expect($page->page()->evaluate('() => document.documentElement.scrollHeight <= innerHeight && document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        if ($width >= 1280) {
            expect($page->page()->evaluate('() => document.querySelector("[data-testid=screen-join] img").getBoundingClientRect().width'))->toBeGreaterThan(250);
            expect($page->page()->evaluate('() => parseFloat(getComputedStyle(document.querySelector(".screen-manual-url")).fontSize)'))->toBeGreaterThan(22);
        }
        $page->assertNoJavaScriptErrors()->screenshot(filename: 'screen-join-'.$width.'-'.$height);
    } finally {
        $room->delete();
    }
})->with([[1920, 1080], [1280, 720], [844, 390], [390, 844]]);
