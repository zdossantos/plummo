<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\ContentImport;
use App\Models\User;
use App\Services\ContentImportPreview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('reviews an import and explicitly confirms valid rows from the browser', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $word = 'Import'.bin2hex(random_bytes(4));
    $file = UploadedFile::fake()->createWithContent('words.csv', "mot,tags\n".$word.",\n");
    $path = $file->store('imports', 'local');
    $batch = ContentImport::create(['user_id' => $admin->id, 'type' => ContentType::Drawing, 'table_name' => 'words.csv', 'table_path' => $path, 'audios' => [], 'preview' => app(ContentImportPreview::class)->read(ContentType::Drawing, $file, []), 'expires_at' => now()->addHour()]);
    try {
        $page = visit('/admin/login')->withLocale('en-US')->fill('admin-email', $admin->email)->fill('admin-password', 'password')->click('Sign in')->assertSee('Welcome to Plummo administration.');
        $page->navigate('/admin/imports/'.$batch->id)->assertSee($word)->click('Add and publish valid rows')->assertSee('1 contents added')->assertNoJavaScriptErrors();
        expect(Content::where('payload->word', $word)->firstOrFail()->published)->toBeTrue();
    } finally {
        Content::where('payload->word', $word)->delete();
        Storage::disk('local')->delete($path);
        $batch->delete();
        $admin->delete();
    }
});
