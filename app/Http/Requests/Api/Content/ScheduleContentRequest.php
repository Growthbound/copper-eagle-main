<?php

namespace App\Http\Requests\Api\Content;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'revision_id' => ['required', 'integer'],
            'publish_at' => [
                'required',
                'date',
                'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/',
            ],
        ];
    }
}
