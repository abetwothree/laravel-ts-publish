<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Exercises all utility-category validation rules:
 * anyOf, bail, exclude, exclude_if, exclude_unless, exclude_with,
 * exclude_without, filled, missing, missing_if, missing_unless,
 * missing_with, missing_with_all, nullable, present, present_if,
 * present_unless, present_with, present_with_all, prohibited,
 * prohibited_if, prohibited_if_accepted, prohibited_if_declined,
 * prohibited_unless, prohibits, required, required_if,
 * required_if_accepted, required_if_declined, required_unless,
 * required_with, required_with_all, required_without,
 * required_without_all, required_array_keys, sometimes.
 */
class UtilityRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // anyOf — must satisfy any one of the provided rule sets
            'contact' => ['required', Rule::anyOf([
                ['email'],
                ['string', 'regex:/^\+?[0-9]{7,15}$/'],
            ])],

            // bail — stop validating remaining rules on first failure
            'title' => ['bail', 'required', 'string', 'max:255'],

            // exclude — field is excluded from validated output
            'internal_token' => ['nullable', 'string', 'exclude'],

            // exclude_if — excluded when another field equals a given value
            'guest_name' => ['nullable', 'string', 'exclude_if:is_authenticated,true'],

            // exclude_unless — excluded unless another field equals a given value
            'admin_note' => ['nullable', 'string', 'exclude_unless:role,admin'],

            // exclude_with — excluded when another field is present
            'nickname' => ['nullable', 'string', 'exclude_with:display_name'],

            // exclude_without — excluded when another field is absent
            'secondary_email' => ['nullable', 'email', 'exclude_without:primary_email'],

            // filled — must not be empty when present
            'bio' => ['filled', 'string', 'max:1000'],

            // missing — must not be present in the input at all
            'honeypot' => ['missing'],

            // missing_if — must not be present when another field equals a value
            'debug_info' => ['missing_if:environment,production'],

            // missing_unless — must not be present unless another field equals a value
            'dev_flags' => ['missing_unless:environment,development'],

            // missing_with — must not be present when any of the listed fields are present
            'legacy_field' => ['missing_with:new_field'],

            // missing_with_all — must not be present when all listed fields are present
            'deprecated_option' => ['missing_with_all:option_a,option_b'],

            // nullable — field may be null
            'middle_name' => ['nullable', 'string', 'max:50'],

            // present — field must exist in the input (even if null or empty)
            'search_query' => ['present', 'nullable', 'string'],

            // present_if — must be present when another field equals a value
            'filter_value' => ['present_if:has_filter,true', 'nullable', 'string'],

            // present_unless — must be present unless another field equals a value
            'default_sort' => ['present_unless:sort_field,none', 'nullable', 'string'],

            // present_with — must be present when any of the listed fields are present
            'sort_direction' => ['present_with:sort_field', 'nullable', 'string'],

            // present_with_all — must be present when all listed fields are present
            'pagination_size' => ['present_with_all:page,sort_field', 'nullable', 'integer'],

            // prohibited — must be missing or empty
            'admin_override' => ['prohibited'],

            // prohibited_if — must be missing/empty when another field equals a value
            'coupon_code' => ['prohibited_if:is_free,true', 'nullable', 'string'],

            // prohibited_if_accepted — must be missing/empty when another field is accepted
            'trial_extension' => ['prohibited_if_accepted:is_paid_subscriber'],

            // prohibited_if_declined — must be missing/empty when another field is declined
            'free_tier_feature' => ['prohibited_if_declined:accept_free_terms'],

            // prohibited_unless — must be missing/empty unless another field equals a value
            'premium_feature' => ['prohibited_unless:plan,premium', 'nullable', 'string'],

            // prohibits — if present, the listed fields must be absent
            'pay_with_card' => ['nullable', 'boolean', 'prohibits:pay_with_bank_transfer'],

            // required — must be present and not empty
            'name' => ['required', 'string', 'max:255'],

            // required_if — required when another field equals a value
            'notification_email' => ['required_if:send_notifications,true', 'nullable', 'email'],

            // required_if_accepted — required when another field is accepted
            'phone_number' => ['required_if_accepted:send_sms', 'nullable', 'string'],

            // required_if_declined — required when another field is declined
            'reason_for_declining' => ['required_if_declined:accept_terms', 'nullable', 'string'],

            // required_unless — required unless another field equals a value
            'password_reset_token' => ['required_unless:use_sso,true', 'nullable', 'string'],

            // required_with — required when any of the listed fields are present
            'address_line_2' => ['required_with:address_line_1', 'nullable', 'string'],

            // required_with_all — required when all of the listed fields are present
            'full_address' => ['required_with_all:city,postal_code', 'nullable', 'string'],

            // required_without — required when any of the listed fields are absent
            'mobile' => ['required_without:landline', 'nullable', 'string'],

            // required_without_all — required when all of the listed fields are absent
            'contact_method' => ['required_without_all:email,mobile', 'nullable', 'string'],

            // required_array_keys — array must contain at least these keys
            'permissions' => ['required', 'array', 'required_array_keys:read,write'],

            // sometimes — only validated when present in the input
            'optional_preference' => ['sometimes', 'string', 'in:light,dark,system'],

            // Helper fields referenced by other rules above
            'is_authenticated' => ['required', 'boolean'],
            'role' => ['required', 'string', 'in:user,admin,moderator'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'primary_email' => ['required', 'email'],
            'environment' => ['required', 'string', 'in:development,staging,production'],
            'new_field' => ['nullable', 'string'],
            'option_a' => ['nullable', 'string'],
            'option_b' => ['nullable', 'string'],
            'has_filter' => ['required', 'boolean'],
            'sort_field' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'is_free' => ['required', 'boolean'],
            'is_paid_subscriber' => ['required', 'boolean'],
            'accept_free_terms' => ['required', 'boolean'],
            'plan' => ['required', 'string', 'in:free,basic,premium'],
            'pay_with_bank_transfer' => ['nullable', 'boolean'],
            'send_notifications' => ['required', 'boolean'],
            'send_sms' => ['required', 'boolean'],
            'accept_terms' => ['required', 'boolean'],
            'use_sso' => ['required', 'boolean'],
            'address_line_1' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string'],
            'landline' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'mobile_optional' => ['nullable', 'string'],
        ];
    }
}
