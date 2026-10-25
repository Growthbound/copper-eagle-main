<?php

namespace App\Http\Requests\Api\Content;

use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identity' => ['required', 'array'],
            'identity.kind' => ['required', Rule::enum(ContentKind::class)],
            'identity.slug' => ['required', 'string', 'max:255'],
            'identity.title' => ['required', 'string', 'max:255'],
            'identity.template' => ['required', Rule::enum(PageTemplate::class)],
            'payload' => ['required', 'array'],
        ];
    }
}
