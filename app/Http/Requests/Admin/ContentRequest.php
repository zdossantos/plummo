<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('tag_ids')) {
            $this->merge(['tag_ids' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->boolean('published') ? 'required' : 'nullable';
        $rules = [
            'type' => ['required', Rule::enum(ContentType::class)],
            'published' => ['required', 'boolean'],
            'payload' => ['present', 'array'],
            'tag_ids' => ['present', 'array', 'max:30'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'audio' => ['nullable', $this->input('type') === 'blind_test' ? 'nullable' : 'prohibited', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:20480'],
        ];
        $specific = match ($this->input('type')) {
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

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $content = $this->route('content');
            $existing = $content instanceof Content && $content->type === ContentType::BlindTest ? ($content->payload['audio_path'] ?? null) : null;
            if ($this->input('type') === 'blind_test' && $this->boolean('published') && ! $this->hasFile('audio') && ! $existing) {
                $validator->errors()->add('audio', __('admin.audio_required'));
            }
        }];
    }
}
