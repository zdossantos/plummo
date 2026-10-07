<?php

use App\Enums\ContentType;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Requests\Admin\ContentRequest;
use App\Models\Content;
use App\Models\User;
use App\Services\ContentCatalog;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

it('protects every catalogue mutation from players and ordinary accounts', function () {
    $this->get('/admin/contents')->assertRedirect('/admin/login');
    $this->postJson('/admin/tags', ['name' => 'Cinéma'])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->get('/admin/contents')->assertForbidden();
    $this->postJson('/admin/packs', ['name' => 'Cinéma', 'tag_ids' => [1]])->assertForbidden();
});

it('keeps incomplete drafts and validates publication without accepting client audio paths', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $draft = $this->postJson('/admin/contents', ['type' => 'quiz', 'published' => false, 'payload' => [], 'tag_ids' => []])->assertCreated()->json('id');
    $this->patchJson('/admin/contents/'.$draft, ['type' => 'quiz', 'published' => true, 'payload' => [], 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('payload.question');
    $this->postJson('/admin/contents', ['type' => 'quiz', 'published' => true, 'payload' => ['question' => 'Capitale ?', 'choices' => ['Paris', 'Paris', 'Lyon', 'Nice'], 'correct' => 0], 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('payload.choices.0');
    $this->postJson('/admin/contents', ['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Titre', 'artist' => 'Artiste', 'audio_path' => 'audio/client-supplied.wav'], 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('audio');
    $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chat'], 'tag_ids' => [999999]])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');
});

it('requires every pack tag and deduplicates overlapping packs while excluding drafts and other games', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $cinema = $this->postJson('/admin/tags', ['name' => 'Cinéma'])->assertCreated()->json('id');
    $years = $this->postJson('/admin/tags', ['name' => 'Années 2000'])->assertCreated()->json('id');
    $single = $this->postJson('/admin/packs', ['name' => 'Cinéma', 'tag_ids' => [$cinema]])->assertCreated()->json('id');
    $both = $this->postJson('/admin/packs', ['name' => 'Cinéma 2000', 'tag_ids' => [$cinema, $years]])->assertCreated()->json('id');
    $a = $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chat'], 'tag_ids' => [$cinema, $years]])->assertCreated()->json('id');
    $b = $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chien'], 'tag_ids' => [$cinema]])->assertCreated()->json('id');
    $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => false, 'payload' => ['word' => 'Lapin'], 'tag_ids' => [$cinema, $years]])->assertCreated();
    $this->postJson('/admin/contents', ['type' => 'phrase', 'published' => true, 'payload' => ['prompt' => 'Chez moi,'], 'tag_ids' => [$cinema, $years]])->assertCreated();
    $catalog = app(ContentCatalog::class);
    expect($catalog->query(ContentType::Drawing, [$both])->pluck('id')->all())->toBe([$a]);
    expect($catalog->query(ContentType::Drawing, [$single, $both])->orderBy('id')->pluck('id')->all())->toBe([$a, $b]);
    $this->deleteJson('/admin/tags/'.$cinema)->assertConflict();
    $this->postJson('/admin/packs', ['name' => 'Vide', 'tag_ids' => []])->assertUnprocessable();
});

it('stores real audio privately and cleans replacements while ignoring a fabricated path', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $wav = 'RIFF'.pack('V', 36 + 800).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 800).str_repeat("\0", 800);
    $create = ['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Titre', 'artist' => 'Artiste', 'audio_path' => 'forged.wav'], 'tag_ids' => [], 'audio' => UploadedFile::fake()->createWithContent('sample.wav', $wav)];
    $result = $this->postJson('/admin/contents', $create)->assertCreated();
    $id = $result->json('id');
    $path = $result->json('payload.audio_path');
    expect($path)->not->toBe('forged.wav');
    Storage::disk('local')->assertExists($path);
    $this->get('/admin/contents/'.$id.'/audio')->assertOk();
    $create['audio'] = UploadedFile::fake()->createWithContent('second.wav', $wav);
    $updated = $this->patchJson('/admin/contents/'.$id, $create)->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->deleteJson('/admin/contents/'.$id)->assertNoContent();
    Storage::disk('local')->assertMissing($updated->json('payload.audio_path'));
    $file = tempnam(sys_get_temp_dir(), 'plummo-audio-test');
    file_put_contents($file, '<?php echo 1;');
    try {
        $create['audio'] = new UploadedFile($file, 'bad.mp3', null, null, true);
        $this->postJson('/admin/contents', $create)->assertUnprocessable()->assertJsonValidationErrors('audio');
    } finally {
        unlink($file);
    }
});

it('updates and removes catalogue entries without exposing them to guests', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $tag = $this->postJson('/admin/tags', ['name' => 'Avant'])->assertCreated()->json('id');
    $this->patchJson('/admin/tags/'.$tag, ['name' => 'Après'])->assertOk();
    $pack = $this->postJson('/admin/packs', ['name' => 'Pack', 'tag_ids' => [$tag]])->assertCreated()->json('id');
    $this->patchJson('/admin/packs/'.$pack, ['name' => 'Pack modifié', 'tag_ids' => [$tag]])->assertOk();
    $content = $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chat'], 'tag_ids' => [$tag]])->assertCreated()->json('id');
    $this->patchJson('/admin/contents/'.$content, ['type' => 'phrase', 'published' => true, 'payload' => ['prompt' => 'Un beau jour,'], 'tag_ids' => [$tag]])->assertOk()->assertJsonPath('type', 'phrase');
    $this->withoutVite()->get('/admin/contents?type=phrase&search=beau')->assertOk();
    $this->deleteJson('/admin/contents/'.$content)->assertNoContent();
    $this->deleteJson('/admin/packs/'.$pack)->assertNoContent();
    $this->deleteJson('/admin/tags/'.$tag)->assertNoContent();
    $this->assertDatabaseMissing('contents', ['id' => $content]);
    $this->assertDatabaseMissing('content_tag', ['content_id' => $content]);
});

it('retains the current audio when saving from a stale route-bound content model', function () {
    Storage::fake('local');
    $content = Content::create(['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Titre', 'artist' => 'Artiste', 'audio_path' => 'audio/old.wav']]);
    $stale = $content->fresh();
    $content->update(['payload' => ['title' => 'Titre', 'artist' => 'Artiste', 'audio_path' => 'audio/current.wav']]);
    Storage::disk('local')->put('audio/current.wav', 'current');
    $request = Mockery::mock(ContentRequest::class);
    $request->shouldReceive('validated')->once()->andReturn(['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Nouveau titre', 'artist' => 'Artiste'], 'tag_ids' => []]);
    $request->shouldReceive('file')->with('audio')->once()->andReturn(null);
    $request->shouldReceive('expectsJson')->once()->andReturn(true);
    app(ContentController::class)->update($request, $stale);
    expect($content->fresh()->payload['audio_path'])->toBe('audio/current.wav');
    Storage::disk('local')->assertExists('audio/current.wav');
});

it('refuses publication when the audio cannot be stored', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::partialMock()->shouldReceive('disk')->with('local')->andReturn($disk);
    $this->postJson('/admin/contents', ['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Titre', 'artist' => 'Artiste'], 'tag_ids' => [], 'audio' => UploadedFile::fake()->create('sample.mp3', 1, 'audio/mpeg')])->assertUnprocessable()->assertJsonValidationErrors('audio');
    $this->assertDatabaseCount('contents', 0);
});

it('accepts an omitted empty tag selection from multipart serialization', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->postJson('/admin/contents', ['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chat']])->assertCreated()->assertJsonCount(0, 'tags');
});

it('filters the catalogue by publication status', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Content::create(['type' => 'drawing', 'published' => true, 'payload' => ['word' => 'Chat']]);
    $draft = Content::create(['type' => 'drawing', 'published' => false, 'payload' => ['word' => 'Chien']]);
    $this->withoutVite()->get('/admin/contents?status=draft')->assertInertia(fn ($page) => $page->has('contents.data', 1)->where('contents.data.0.id', $draft->id));
});
