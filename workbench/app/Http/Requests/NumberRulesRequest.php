<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exercises all number-category validation rules:
 * between, decimal, different, digits, digits_between, gt, gte,
 * integer, lt, lte, max, max_digits, min, min_digits, multiple_of,
 * numeric, same, size.
 */
class NumberRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // between — numeric value must be within the given range
            'score' => ['required', 'integer', 'between:0,100'],

            // decimal — must be numeric with the specified number of decimal places
            'price' => ['required', 'decimal:2'],

            // decimal with range — between 2 and 4 decimal places
            'exchange_rate' => ['required', 'decimal:2,4'],

            // different — must have a different value from another field
            'sale_price' => ['required', 'numeric', 'different:price'],

            // digits — integer must have the exact number of digits
            'pin' => ['required', 'digits:4'],

            // digits_between — integer must have a digit count within the range
            'verification_code' => ['required', 'digits_between:4,6'],

            // gt — must be greater than another field's value
            'max_price' => ['required', 'numeric', 'gt:price'],

            // gte — must be greater than or equal to another field's value
            'discounted_price' => ['required', 'numeric', 'gte:sale_price'],

            // integer — must be an integer
            'quantity' => ['required', 'integer'],

            // integer:strict — value type must be integer (no numeric strings)
            'item_count' => ['required', 'integer:strict'],

            // lt — must be less than another field's value
            'min_age' => ['required', 'integer', 'lt:max_age'],

            // lte — must be less than or equal to another field's value
            'min_age_inclusive' => ['required', 'integer', 'lte:max_age'],

            // numeric counterpart used in gt/lt comparisons above
            'max_age' => ['required', 'integer'],

            // max — must not exceed the given value
            'retry_count' => ['required', 'integer', 'max:10'],

            // max_digits — integer must not have more digits than given
            'account_number' => ['required', 'integer', 'max_digits:12'],

            // min — must be at least the given value
            'page' => ['required', 'integer', 'min:1'],

            // min_digits — integer must have at least this many digits
            'tracking_code' => ['required', 'integer', 'min_digits:5'],

            // multiple_of — must be a multiple of the given value
            'batch_size' => ['required', 'integer', 'multiple_of:10'],

            // numeric — must be numeric (int, float, or numeric string)
            'amount' => ['required', 'numeric'],

            // numeric:strict — value must be an actual integer or float type
            'strict_amount' => ['required', 'numeric:strict'],

            // same — must equal the given field's value
            'confirm_quantity' => ['required', 'integer', 'same:quantity'],

            // size — must equal the given value exactly
            'team_size' => ['required', 'integer', 'size:5'],
        ];
    }
}
