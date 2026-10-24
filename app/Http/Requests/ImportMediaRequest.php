<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.source_url' => ['required', 'url:http,https', 'max:2048', 'distinct'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.alt' => ['nullable', 'string', 'max:500'],
            'items.*.handle' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (['title', 'alt', 'handle'] as $field) {
                if (! array_key_exists($field, $item) || ! is_string($item[$field])) {
                    continue;
                }

                $value = trim($item[$field]);
                $items[$index][$field] = $value === '' ? null : $value;
            }
        }

        $this->merge(['items' => $items]);
    }
}
