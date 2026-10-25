<?php

namespace App\Http\Requests\Api\Content;

use Illuminate\Foundation\Http\FormRequest;

final class RollbackContentRequest extends FormRequest
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
        ];
    }
}
