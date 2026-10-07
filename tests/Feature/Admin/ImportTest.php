<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\ContentImport;
use App\Models\Tag;
use App\Models\User;
use App\Services\ContentImportPreview;
use App\Services\ContentImports;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

uses(DatabaseTransactions::class);

it('previews CSV rows without creating content and reports unknown tags', function () {
    Tag::create(['name' => 'Cinéma']);
    $file = UploadedFile::fake()->createWithContent('words.csv', "mot,tags\nChat,Cinéma\nChien,Inconnu\n");
    $rows = app(ContentImportPreview::class)->read(ContentType::Drawing, $file, []);
    expect($rows['rows'])->toHaveCount(2);
    expect($rows['rows'][0]['errors'])->toBe([]);
    expect($rows['rows'][1]['errors'])->not->toBeEmpty();
    $this->assertDatabaseCount('contents', 0);
});

it('reads the first Excel sheet and preserves quoted CSV text', function () {
    $path = tempnam(sys_get_temp_dir(), 'plummo-xlsx');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['mot', 'tags']));
    $writer->addRow(Row::fromValues(['Chat', '']));
    $writer->close();
    try {
        $rows = app(ContentImportPreview::class)->read(ContentType::Drawing, new UploadedFile($path, 'words.xlsx', null, null, true), []);
        expect($rows['rows'][0]['payload']['word'])->toBe('Chat');
    } finally {
        unlink($path);
    }
    $file = UploadedFile::fake()->createWithContent('phrases.csv', "debut_phrase;tags\n\"Un jour,\nchez moi\";\n");
    expect(app(ContentImportPreview::class)->read(ContentType::Phrase, $file, [])['rows'][0]['payload']['prompt'])->toBe("Un jour,\nchez moi");
});

it('reports missing and ambiguous music files and unused uploads', function () {
    $file = UploadedFile::fake()->createWithContent('music.csv', "titre,artiste,fichier_audio,tags\nTitre,Artiste,absent.wav,\nAutre,Artiste,double.wav,\n");
    $rows = app(ContentImportPreview::class)->read(ContentType::BlindTest, $file, [
        ['name' => 'double.wav', 'path' => 'a.wav'], ['name' => 'double.wav', 'path' => 'b.wav'], ['name' => 'unused.wav', 'path' => 'c.wav'],
    ]);
    expect($rows['rows'][0]['errors'])->not->toBeEmpty();
    expect($rows['rows'][1]['errors'])->not->toBeEmpty();
    expect($rows['unused'])->toContain('unused.wav');
});

it('requires confirmation and imports only valid rows once for the owning administrator', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->get('/admin/imports')->assertRedirect('/admin/login');
    $this->actingAs($admin);
    $file = UploadedFile::fake()->createWithContent('words.csv', "mot,tags\nChat,\nChien,Inconnu\n");
    $id = $this->postJson('/admin/imports', ['type' => 'drawing', 'table' => $file])->assertCreated()->json('id');
    $this->assertDatabaseCount('contents', 0);
    $this->actingAs(User::factory()->create(['is_admin' => true]))->postJson('/admin/imports/'.$id.'/confirm', ['published' => true])->assertNotFound();
    $this->actingAs($admin)->postJson('/admin/imports/'.$id.'/confirm', ['published' => true, 'rows' => [['payload' => ['word' => 'Forged']]]])->assertOk()->assertJsonPath('added', 1)->assertJsonPath('refused', 1);
    $this->assertDatabaseHas('contents', ['type' => 'drawing', 'published' => true]);
    expect(Content::first()->payload['word'])->toBe('Chat');
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => true])->assertConflict();
});

it('refuses expired previews and invalid confirmation options', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $id = $this->postJson('/admin/imports', ['type' => 'drawing', 'table' => UploadedFile::fake()->createWithContent('words.csv', "mot,tags\nChat,\n")])->assertCreated()->json('id');
    $this->postJson('/admin/imports/'.$id.'/confirm', [])->assertUnprocessable();
    $this->travel(61)->minutes();
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => false])->assertGone();
    $this->assertDatabaseCount('contents', 0);
});

it('warns about existing music independently of its stored audio path', function () {
    Content::create(['type' => 'blind_test', 'published' => true, 'payload' => ['title' => 'Titre', 'artist' => 'Artiste', 'audio_path' => 'audio/existing.wav']]);
    $file = UploadedFile::fake()->createWithContent('music.csv', "titre,artiste,fichier_audio,tags\nTitre,Artiste,clip.wav,\n");
    $preview = app(ContentImportPreview::class)->read(ContentType::BlindTest, $file, [['name' => 'clip.wav', 'path' => 'audio/clip.wav']]);
    expect($preview['rows'][0]['warnings'])->not->toBeEmpty();
});

it('rejects incorrect quiz answers missing headers and oversized row counts', function () {
    $quiz = UploadedFile::fake()->createWithContent('quiz.csv', "question,reponse_a,reponse_b,reponse_c,reponse_d,bonne_reponse,tags\nQ,A,B,C,D,X,\n");
    expect(app(ContentImportPreview::class)->read(ContentType::Quiz, $quiz, [])['rows'][0]['errors'])->not->toBeEmpty();
    expect(fn () => app(ContentImportPreview::class)->read(ContentType::Drawing, UploadedFile::fake()->createWithContent('bad.csv', "mot\nChat\n"), []))->toThrow(ValidationException::class);
    expect(fn () => app(ContentImportPreview::class)->read(ContentType::Drawing, UploadedFile::fake()->createWithContent('large.csv', "mot,tags\n".str_repeat("Chat,\n", 501)), []))->toThrow(ValidationException::class);
});

it('imports matched audio privately and removes temporary files after confirmation', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $wav = 'RIFF'.pack('V', 836).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 800).str_repeat("\0", 800);
    $id = $this->postJson('/admin/imports', ['type' => 'blind_test', 'table' => UploadedFile::fake()->createWithContent('music.csv', "titre,artiste,fichier_audio,tags\nTitre,Artiste,clip.wav,\n"), 'audios' => [UploadedFile::fake()->createWithContent('clip.wav', $wav)]])->assertCreated()->json('id');
    $batch = ContentImport::findOrFail($id);
    $this->get('/admin/imports/'.$id.'/audio/2')->assertOk();
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => true])->assertOk()->assertJsonPath('added', 1);
    $content = Content::first();
    Storage::disk('local')->assertExists($content->payload['audio_path']);
    Storage::disk('local')->assertMissing($batch->table_path);
    Storage::disk('local')->assertMissing($batch->audios[0]['path']);
    $this->get('/admin/imports/'.$id.'/audio/2')->assertGone();
});

it('revalidates tags at confirmation and prunes expired previews', function () {
    Storage::fake('local');
    $tag = Tag::create(['name' => 'Test import']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $id = $this->postJson('/admin/imports', ['type' => 'drawing', 'table' => UploadedFile::fake()->createWithContent('words.csv', "mot,tags\nChat,Test import\n")])->assertCreated()->json('id');
    $tag->delete();
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => false])->assertOk()->assertJsonPath('added', 0)->assertJsonPath('refused', 1);
    $this->travel(61)->minutes();
    expect(app(ContentImports::class)->prune())->toBe(1);
    $this->assertDatabaseMissing('content_imports', ['id' => $id]);
});

it('reports unsupported Excel cell types as row errors instead of failing the preview', function () {
    $path = tempnam(sys_get_temp_dir(), 'plummo-date');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['mot', 'tags']));
    $writer->addRow(Row::fromValuesWithStyles([new DateTimeImmutable('2026-01-01'), ''], [0 => new Style(format: 'yyyy-mm-dd')]));
    $writer->close();
    try {
        $preview = app(ContentImportPreview::class)->read(ContentType::Drawing, new UploadedFile($path, 'date.xlsx', null, null, true), []);
        expect($preview['rows'][0]['errors'])->not->toBeEmpty();
    } finally {
        unlink($path);
    }
});
