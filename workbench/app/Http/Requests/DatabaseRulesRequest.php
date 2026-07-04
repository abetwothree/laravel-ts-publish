<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Exercises all database-category validation rules:
 * exists, unique.
 */
class DatabaseRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // exists — field value must exist in the given database table/column
            'state' => ['required', 'string', 'exists:states,abbreviation'],

            // exists with Eloquent model reference
            'category_id' => ['required', 'integer', 'exists:categories,id'],

            // exists using Rule::exists() fluent builder
            'country_code' => [
                'required',
                'string',
                'size:2',
                Rule::exists('countries', 'code'),
            ],

            // unique — field value must not already exist in the given table/column
            'email' => ['required', 'email', 'unique:users,email'],

            // unique — username must be unique in the users table
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],

            // unique using Rule::unique() fluent builder with ignore
            'phone' => [
                'nullable',
                'string',
                Rule::unique('users', 'phone_number'),
            ],

            // exists with nullable — optional reference to an existing record
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
