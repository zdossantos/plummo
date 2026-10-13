<?php

use App\Models\Content;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

it('validates editable bluff truths and filters the catalogue', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $id = $this->postJson('/admin/contents', ['type' => 'bluff', 'published' => false, 'payload' => [], 'tag_ids' => []])->assertCreated()->json('id');
    $this->patchJson('/admin/contents/'.$id, ['type' => 'bluff', 'published' => true, 'payload' => ['question' => 'Une question ?', 'answer' => '---', 'format' => 'unknown'], 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors(['payload.answer', 'payload.format']);
    $payload = ['question' => 'Que signifie ce mot ?', 'answer' => 'Une petite plume', 'format' => 'definition'];
    $this->patchJson('/admin/contents/'.$id, ['type' => 'bluff', 'published' => true, 'payload' => $payload + ['correct' => 1], 'tag_ids' => []])->assertOk()->assertJsonPath('payload', $payload);
    $this->withoutVite()->get('/admin/contents?type=bluff')->assertInertia(fn ($page) => $page->has('contents.data', 1)->where('contents.data.0.id', $id));
});

it('previews and imports tagged bluff questions and truths with invalid rows refused', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $tag = Tag::create(['name' => 'Culture']);
    $this->get('/admin/imports/template/bluff')->assertOk()->assertStreamedContent("question,reponse,format,tags\n");
    $file = UploadedFile::fake()->createWithContent('bluff.csv', "question,reponse,format,tags\nQue signifie ce mot ?,Une petite plume,definition,Culture\nAutre question ?,Vérité,inconnu,Culture\n");
    $id = $this->postJson('/admin/imports', ['type' => 'bluff', 'table' => $file])->assertCreated()->json('id');
    $this->assertDatabaseCount('contents', 0);
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => true])->assertOk()->assertJsonPath('added', 1)->assertJsonPath('refused', 1);
    expect(Content::sole()->payload)->toEqual(['question' => 'Que signifie ce mot ?', 'answer' => 'Une petite plume', 'format' => 'definition'])->and(Content::sole()->tags->modelKeys())->toBe([$tag->id]);
});
