<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workbench\App\Enums\MediaType;

/**
 * Exercises all string-category validation rules:
 * active_url, alpha, alpha_dash, alpha_num, ascii, confirmed,
 * current_password, different, doesnt_end_with, doesnt_start_with,
 * email, ends_with, enum, hex_color, in, ip, ipv4, ipv6, json,
 * lowercase, mac_address, max, min, not_in, not_regex, regex,
 * same, size, starts_with, string, uppercase, url, ulid, uuid.
 */
class StringRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // active_url — must have a valid DNS A or AAAA record
            'website' => ['required', 'active_url'],

            // alpha — entirely Unicode alphabetic characters
            'first_name' => ['required', 'alpha'],

            // alpha_dash — alpha-numeric characters plus dashes and underscores
            'username' => ['required', 'alpha_dash'],

            // alpha_num — entirely alpha-numeric characters
            'reference_code' => ['required', 'alpha_num'],

            // ascii — entirely 7-bit ASCII characters
            'ascii_id' => ['required', 'ascii'],

            // confirmed — must have a matching {field}_confirmation field
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // current_password — must match the authenticated user's password
            'old_password' => ['required', 'current_password'],

            // different — must have a different value from another field
            'new_password' => ['required', 'string', 'min:8', 'different:old_password'],

            // doesnt_start_with — must not start with any of the given values
            'slug' => ['required', 'string', 'doesnt_start_with:_,-'],

            // doesnt_end_with — must not end with any of the given values
            'path' => ['required', 'string', 'doesnt_end_with:_,-'],

            // email — formatted as a valid email address
            'email' => ['required', 'email'],

            // ends_with — must end with one of the given values
            'status_suffix' => ['required', 'string', 'ends_with:_active,_inactive'],

            // enum — must be a valid backed enum case
            'media_type' => ['required', Rule::enum(MediaType::class)],

            // hex_color — must be a valid hexadecimal color
            'brand_color' => ['required', 'hex_color'],

            // in — must be included in the given list
            'country_code' => ['required', Rule::in(['US', 'CA', 'UK', 'AU'])],

            // ip — must be a valid IP address (v4 or v6)
            'ip_address' => ['nullable', 'ip'],

            // ipv4 — must be a valid IPv4 address
            'ipv4_address' => ['nullable', 'ipv4'],

            // ipv6 — must be a valid IPv6 address
            'ipv6_address' => ['nullable', 'ipv6'],

            // json — must be a valid JSON string
            'metadata' => ['nullable', 'json'],

            // lowercase — must be all lowercase characters
            'locale_code' => ['required', 'string', 'lowercase'],

            // mac_address — must be a valid MAC address
            'device_mac' => ['nullable', 'mac_address'],

            // max — must not exceed the given maximum length
            'title' => ['required', 'string', 'max:255'],

            // min — must meet the given minimum length
            'bio' => ['required', 'string', 'min:10'],

            // not_in — must not be in the given list
            'topping' => ['required', 'string', Rule::notIn(['sprinkles', 'cherries'])],

            // regex — must match the given regular expression
            'postal_code' => ['required', 'regex:/^[A-Z][0-9][A-Z] ?[0-9][A-Z][0-9]$/i'],

            // not_regex — must not match the given regular expression
            'clean_text' => ['required', 'string', 'not_regex:/[<>]/'],

            // same — must match the value of the specified field
            'email_confirm' => ['required', 'email', 'same:email'],

            // size — must be exactly this many characters
            'iso_country_code' => ['required', 'string', 'size:2'],

            // starts_with — must start with one of the given values
            'honorific' => ['required', 'string', 'starts_with:Mr,Ms,Dr,Prof'],

            // string — must be a string
            'description' => ['required', 'string'],

            // uppercase — must be all uppercase characters
            'currency_code' => ['required', 'string', 'uppercase', 'size:3'],

            // url — must be a valid URL
            'homepage' => ['nullable', 'url'],

            // ulid — must be a valid ULID
            'external_id' => ['nullable', 'ulid'],

            // uuid — must be a valid UUID (any version)
            'request_id' => ['nullable', 'uuid'],
        ];
    }
}
