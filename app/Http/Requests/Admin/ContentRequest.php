<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentType;
use App\Models\Content;
use App\Services\ContentRules;
use Illuminate\Foundation\Http\FormRequest;
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
        return ContentRules::rules((string) $this->input('type'), $this->boolean('published'));
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
