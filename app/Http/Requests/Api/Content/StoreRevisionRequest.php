<?php

namespace App\Http\Requests\Api\Content;

use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'array'],
            'summary' => ['nullable', 'string', 'max:255'],
            'identity' => ['sometimes', 'array'],
            'identity.kind' => ['required_with:identity', Rule::enum(ContentKind::class)],
            'identity.slug' => ['required_with:identity', 'string', 'max:255'],
            'identity.title' => ['required_with:identity', 'string', 'max:255'],
            'identity.template' => ['required_with:identity', Rule::enum(PageTemplate::class)],
        ];
    }
}
