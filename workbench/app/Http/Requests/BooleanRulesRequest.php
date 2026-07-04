<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exercises all boolean-category validation rules:
 * accepted, accepted_if, boolean, declined, declined_if.
 */
class BooleanRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // accepted — must be "yes", "on", 1, "1", true, or "true"
            'terms_accepted' => ['accepted'],

            // accepted_if — must be accepted if another field equals a given value
            'newsletter_accepted' => ['accepted_if:terms_accepted,true'],

            // boolean — must be castable to a boolean (true/false/1/0/"1"/"0")
            'is_active' => ['boolean'],

            // nullable + boolean — boolean or null
            'is_archived' => ['nullable', 'boolean'],

            // boolean:strict — only true or false (no truthy/falsy strings)
            'is_featured' => ['boolean:strict'],

            // declined — must be "no", "off", 0, "0", false, or "false"
            'marketing_declined' => ['declined'],

            // declined_if — must be declined if another field equals a given value
            'privacy_declined' => ['declined_if:is_active,false'],
        ];
    }
}
