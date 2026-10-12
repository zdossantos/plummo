<?php

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use App\Models\Tag;
use App\Models\User;
use App\Services\ContentCatalog;
use Database\Seeders\TribunalCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

it('manages tribunal subjects with publication validation and catalogue filtering', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $draft = $this->postJson('/admin/contents', ['type' => 'tribunal', 'published' => false, 'payload' => [], 'tag_ids' => []])->assertCreated()->json('id');
    $this->patchJson('/admin/contents/'.$draft, ['type' => 'tribunal', 'published' => true, 'payload' => [], 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('payload.question');
    $this->patchJson('/admin/contents/'.$draft, ['type' => 'tribunal', 'published' => true, 'payload' => ['question' => 'Les chaussettes devraient-elles avoir des vacances ?', 'correct' => 1], 'tag_ids' => []])->assertOk()->assertJsonPath('payload', ['question' => 'Les chaussettes devraient-elles avoir des vacances ?']);
    $this->withoutVite()->get('/admin/contents?type=tribunal')->assertInertia(fn ($page) => $page->has('contents.data', 1)->where('contents.data.0.id', $draft));
    $this->deleteJson('/admin/contents/'.$draft)->assertNoContent();
});

it('imports tagged tribunal subjects with preview and confirmation using the downloadable template', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $tag = Tag::create(['name' => 'Vie quotidienne']);
    $this->get('/admin/imports/template/tribunal')->assertOk()->assertStreamedContent("sujet,tags\n");
    $file = UploadedFile::fake()->createWithContent('tribunal.csv', "sujet,tags\nFaut-il interdire les réveils ?,Vie quotidienne\nAutre sujet ?,Inconnu\n");
    $id = $this->postJson('/admin/imports', ['type' => 'tribunal', 'table' => $file])->assertCreated()->json('id');
    $this->assertDatabaseCount('contents', 0);
    $this->postJson('/admin/imports/'.$id.'/confirm', ['published' => true])->assertOk()->assertJsonPath('added', 1)->assertJsonPath('refused', 1);
    $subject = Content::sole();
    expect($subject->payload)->toBe(['question' => 'Faut-il interdire les réveils ?'])->and($subject->tags->modelKeys())->toBe([$tag->id]);
});

it('bootstraps fifty editable subjects per language without overwriting existing publication choices', function () {
    $this->seed(TribunalCatalogSeeder::class);
    expect(Content::where('type', 'tribunal')->count())->toBe(100);
    $subject = Content::where('type', 'tribunal')->firstOrFail();
    $subject->update(['published' => false]);
    $this->seed(TribunalCatalogSeeder::class);
    expect(Content::where('type', 'tribunal')->count())->toBe(100)->and($subject->fresh()->published)->toBeFalse();
    foreach (['Français', 'English'] as $language) {
        $pack = Pack::where('name', 'Tribunal · '.$language)->sole();
        expect(app(ContentCatalog::class)->query(ContentType::Tribunal, [$pack->id])->count())->toBe($language === 'Français' ? 49 : 50);
    }
});
