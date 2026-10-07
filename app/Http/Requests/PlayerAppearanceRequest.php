<?php

namespace App\Http\Requests;

use App\Services\PlummoCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlayerAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(PlummoCatalog $catalog): array
    {
        return [
            'name' => ['required', 'string', 'max:30'],
            'color' => ['required', Rule::in($catalog->colors())],
            'accessories' => ['present', 'array', 'list', 'max:2'],
            'accessories.*' => ['string', 'distinct', Rule::in(array_keys($catalog->slots()))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $slots = app(PlummoCatalog::class)->slots();
            $chosen = array_map(fn (string $id): string => $slots[$id], $this->input('accessories'));
            if (count(array_unique($chosen)) !== count($chosen)) {
                $validator->errors()->add('accessories', __('rooms.invalid_accessories'));
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.*' => __('rooms.invalid_name'), 'color.*' => __('rooms.invalid_color'),
            'accessories.*' => __('rooms.invalid_accessories'), 'accessories.*.*' => __('rooms.invalid_accessories')];
    }
}
