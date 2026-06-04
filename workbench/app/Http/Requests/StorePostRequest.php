<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'published' => ['boolean'],
            'rating' => ['nullable', 'numeric'],
            'email' => ['required', 'email'],
            'tags' => ['array'],
            'tags.*' => ['string'],
        ];
    }
}
