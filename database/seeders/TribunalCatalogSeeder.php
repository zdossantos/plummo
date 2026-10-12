<?php

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TribunalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language) {
                $tag = Tag::firstOrCreate(['name' => $language]);
                $pack = Pack::firstOrCreate(['name' => 'Tribunal · '.$language]);
                $pack->tags()->syncWithoutDetaching([$tag->id]);
                // These translations are bootstrap data and a fallback for already active legacy rounds only.
                foreach (__('tribunal.topics', [], $locale) as $question) {
                    $content = Content::firstOrCreate(
                        ['type' => ContentType::Tribunal, 'payload->question' => $question],
                        ['payload' => ['question' => $question], 'published' => true],
                    );
                    $content->tags()->syncWithoutDetaching([$tag->id]);
                }
            }
        });
    }
}
