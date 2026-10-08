<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Validation\ValidationException;

class BlindChoices
{
    private function key(Content $song): string
    {
        return mb_strtolower(trim($song->payload['title']))."\0".mb_strtolower(trim($song->payload['artist']));
    }

    private function label(Content $song): string
    {
        return trim($song->payload['title']).' — '.trim($song->payload['artist']);
    }

    public function count(): int
    {
        return Content::where('type', ContentType::BlindTest)->where('published', true)->get()->unique(fn (Content $song) => $this->key($song))->count();
    }

    /** @return array{choices: list<string>, correct: int} */
    public function prepare(Content $correct): array
    {
        $tags = $correct->tags()->pluck('tags.id')->all();
        $songs = Content::where('type', ContentType::BlindTest)->where('published', true)->with('tags')->get()
            ->reject(fn (Content $song) => $this->key($song) === $this->key($correct));
        [$related, $others] = $songs->partition(fn (Content $song) => $song->tags->pluck('id')->intersect($tags)->isNotEmpty());
        $false = $related->shuffle()->concat($others->shuffle())->unique(fn (Content $song) => $this->key($song))->take(7);
        if ($false->count() !== 7) {
            throw ValidationException::withMessages(['packs' => __('rooms.blind_catalogue_small')]);
        }
        $choices = $false->map(fn (Content $song) => $this->label($song))->push($this->label($correct))->shuffle()->values()->all();

        return ['choices' => $choices, 'correct' => (int) array_search($this->label($correct), $choices, true)];
    }
}
