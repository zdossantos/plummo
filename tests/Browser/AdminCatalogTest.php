<?php

use App\Models\Content;
use App\Models\Pack;
use App\Models\Tag;
use App\Models\User;

it('prepares a tagged drawing word and a dynamic pack through the administration', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $suffix = bin2hex(random_bytes(4));
    $tagName = 'Cinéma '.$suffix;
    $packName = 'Pack '.$suffix;
    $word = 'Chat'.$suffix;
    try {
        $page = visit('/admin/login')->withLocale('en-US')->fill('admin-email', $admin->email)->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->fill('admin-password', 'password')->click('Sign in')->assertSee('Welcome to Plummo administration.');
        $page->click('.admin-nav a[href="/admin/tags"]')->click('Add tag')->fill('#tag-name', $tagName)->click('Add tag')->assertSee($tagName);
        $page->click('.admin-nav a[href="/admin/contents"]')->click('Add content')->select('#content-type', 'drawing')->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->fill('#content-word', $word)->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->check($tagName)->click('Publish')->assertSee('Edit content')->assertNoJavaScriptErrors();
        $content = Content::where('payload->word', $word)->firstOrFail();
        expect($content->published)->toBeTrue();
        expect($content->tags()->pluck('name')->all())->toBe([$tagName]);
        $page->click('Back to content')->click('Add content')->select('#content-type', 'drawing')->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->fill('#content-word', $word.'sans-tag')->click('Publish')->assertSee('Edit content');
        $page->click('.admin-nav a[href="/admin/packs"]')->click('Add pack')->fill('#pack-name', $packName)->click('.page-deck:visible > .page-controls > button[aria-label="Next"]')->check($tagName)->click('Add pack')->assertSee($packName)->assertNoJavaScriptErrors()->screenshot(filename: 'admin-packs');
        expect(Pack::where('name', $packName)->firstOrFail()->tags()->count())->toBe(1);
    } finally {
        Content::whereIn('payload->word', [$word, $word.'sans-tag'])->delete();
        Pack::where('name', $packName)->delete();
        Tag::where('name', $tagName)->delete();
        $admin->delete();
    }
});
