<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exercises all date-category validation rules:
 * after, after_or_equal, before, before_or_equal, date,
 * date_equals, date_format, different, timezone.
 */
class DateRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // date — must be a valid non-relative date per strtotime()
            'event_date' => ['required', 'date'],

            // after — must be a date after the given date
            'start_date' => ['required', 'date', 'after:today'],

            // after_or_equal — must be a date after or equal to given date
            'registration_deadline' => ['required', 'date', 'after_or_equal:start_date'],

            // before — must be a date before the given date
            'birth_date' => ['required', 'date', 'before:today'],

            // before_or_equal — must be a date before or equal to given date
            'end_date' => ['required', 'date', 'before_or_equal:registration_deadline'],

            // date_equals — must equal the given date exactly
            'release_date' => ['required', 'date_equals:2025-01-01'],

            // date_format — must match one of the given date formats
            'formatted_date' => ['required', 'date_format:Y-m-d'],

            // date_format with multiple accepted formats
            'flexible_date' => ['required', 'date_format:Y-m-d,d/m/Y'],

            // different — must differ from another date field
            'follow_up_date' => ['required', 'date', 'different:start_date'],

            // nullable + date — optional date field
            'cancelled_at' => ['nullable', 'date'],

            // timezone — must be a valid timezone identifier
            'user_timezone' => ['required', 'string', 'timezone'],

            // timezone with region restriction
            'us_timezone' => ['nullable', 'string', 'timezone:per_country,US'],
        ];
    }
}
