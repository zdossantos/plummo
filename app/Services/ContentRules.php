<?php

namespace App\Services;

use App\Enums\ContentType;
use Illuminate\Validation\Rule;

class ContentRules
{
    /** @return array<string, mixed> */
    public static function rules(string $type, bool $published): array
    {
        $required = $published ? 'required' : 'nullable';
        $rules = [
            'type' => ['required', Rule::enum(ContentType::class)],
            'published' => ['required', 'boolean'],
            'payload' => ['present', 'array'],
            'tag_ids' => ['present', 'array', 'max:30'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'audio' => ['nullable', $type === 'blind_test' ? 'nullable' : 'prohibited', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:20480'],
        ];
        $specific = match ($type) {
            'quiz' => [
                'payload.question' => [$required, 'string', 'max:500'],
                'payload.choices' => [$required, 'array', 'list', 'size:4'],
                'payload.choices.*' => [$required, 'string', 'max:200', 'distinct:ignore_case'],
                'payload.correct' => [$required, 'integer', 'between:0,3'],
            ],
            'blind_test' => ['payload.title' => [$required, 'string', 'max:200'], 'payload.artist' => [$required, 'string', 'max:200']],
            'drawing' => ['payload.word' => [$required, 'string', 'max:100']],
            'phrase' => ['payload.prompt' => [$required, 'string', 'max:240']],
            default => [],
        };

        return [...$rules, ...$specific];
    }
}
