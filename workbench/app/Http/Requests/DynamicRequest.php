<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DynamicRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Deliberately uses a non-null-safe call on $this->user() so that
        // in a non-HTTP test context (where no user is authenticated),
        // this throws an Error — causing the analyzer to set isDynamic = true.
        $userId = $this->user()->id; // @phpstan-ignore-line

        return [
            'name' => ['required', 'string'],
            'user_id' => ['required', 'integer', 'in:'.$userId],
        ];
    }
}
