<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[TsCasts([
    'status' => "'draft' | 'published' | 'archived'",
    'attributes' => ['type' => 'PostAttributes', 'import' => '@js/types/posts'],
])]
class UpdatePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'priority' => ['nullable', 'integer'],
            'attributes' => ['sometimes', 'array'],
        ];
    }
}
