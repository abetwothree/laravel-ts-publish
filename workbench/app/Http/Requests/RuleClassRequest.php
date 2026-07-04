<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\MembershipLevel;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Address;

/**
 * Exercises the full range of Rule class objects available in Laravel:
 * date, anyOf, contains, doesntContain, dimensions, email, enum (with when/only/except),
 * excludeIf, excludeUnless, exists, in, notIn, prohibitedIf, prohibitedUnless,
 * requiredIf, requiredUnless, string, unique, forEach, numeric, and File::image.
 */
class RuleClassRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', Rule::date()->nowOrFuture()],
            'end_date' => ['nullable', Rule::date()->after('start_date')],
            'username' => [
                'required',
                Rule::anyOf([
                    ['string', 'email'],
                    ['string', 'alpha_dash', 'min:6'],
                ]),
            ],
            'roles' => [
                'required',
                'array',
                Rule::contains(['admin', 'editor']),
            ],
            'invalid_roles' => [
                'required',
                'array',
                Rule::doesntContain(['banned', 'suspended']),
            ],
            'avatar' => [
                'required',
                Rule::dimensions()
                    ->maxWidth(1000)
                    ->maxHeight(500)
                    ->ratio(3 / 2),
            ],
            'email' => [
                'required',
                Rule::email()
                    ->rfcCompliant(strict: false)
                    ->validateMxRecord()
                    ->preventSpoofing(),
            ],
            'order_status' => [
                Rule::enum(OrderStatus::class)
                    ->when(
                        Auth::user()->isAdmin(),
                        fn ($rule) => $rule->only([OrderStatus::Cancelled, OrderStatus::Refunded]),
                        fn ($rule) => $rule->only(OrderStatus::Pending),
                    ),
            ],
            'membership_level' => [Rule::enum(MembershipLevel::class)->only([MembershipLevel::Premium, MembershipLevel::Enterprise])],
            'visibility' => [Rule::enum(Visibility::class)->except([Visibility::Private])],
            'role_id' => [Rule::excludeIf(Auth::user()->isAdmin())],
            'team_id' => [Rule::excludeUnless(Auth::user()->isAdmin())],
            'state' => [Rule::exists('states', 'abbreviation')],
            'zones' => [
                'required',
                Rule::in(['first-zone', 'second-zone']),
            ],
            'airports.*' => Rule::in(['NYC', 'LIT']),
            'toppings' => [
                'required',
                Rule::notIn(['sprinkles', 'cherries']),
            ],
            'role_id_prohibited' => [Rule::prohibitedIf(Auth::user()->isAdmin())],
            'role_id_callback' => [Rule::prohibitedIf(fn () => Auth::user()->isAdmin())],
            'role_id_prohibited_unless' => [Rule::prohibitedUnless(Auth::user()->isAdmin())],
            'role_id_prohibited_unless_callback' => [Rule::prohibitedUnless(fn () => Auth::user()->isAdmin())],
            'role_id_required_if' => [Rule::requiredIf(Auth::user()->isAdmin())],
            'role_id_required_if_callback' => [Rule::requiredIf(fn () => Auth::user()->isAdmin())],
            'role_id_required_unless' => [Rule::requiredUnless(Auth::user()->isAdmin())],
            'role_id_required_unless_callback' => [Rule::requiredUnless(fn () => Auth::user()->isAdmin())],
            'title' => [
                'required',
                Rule::string()
                    ->min(3)
                    ->max(255)
                    ->alphaDash(ascii: true),
            ],
            'email_unique' => [
                'required',
                Rule::unique('users')->ignore(Auth::id()),
            ],
            'addresses.*.id' => Rule::forEach(function (?string $value, string $attribute) {
                return [
                    Rule::exists(Address::class, 'id'),
                ];
            }),
            'photo' => [
                'required',
                File::image()
                    ->min(1024)
                    ->max(12 * 1024)
                    ->dimensions(Rule::dimensions()->maxWidth(1000)->maxHeight(500)),
            ],
            'quantity' => ['required', Rule::numeric()],
            'accent_color' => [Rule::enum(Color::class)->only([Color::Red, Color::Blue])],
            'forbidden_color' => [Rule::enum(Color::class)->except([Color::Red])],
        ];
    }
}
