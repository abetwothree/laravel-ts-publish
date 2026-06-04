/**
 * @see Workbench\App\Http\Requests\UtilityRulesRequest
 */
export interface UtilityRulesRequest {
    contact: string;
    title: string;
    internal_token?: string | null;
    guest_name?: string | null;
    admin_note?: string | null;
    nickname?: string | null;
    /** @format email */
    secondary_email?: string | null;
    bio?: string;
    debug_info?: unknown;
    dev_flags?: unknown;
    legacy_field?: unknown;
    deprecated_option?: unknown;
    middle_name?: string | null;
    search_query?: string | null;
    filter_value?: string | null;
    default_sort?: string | null;
    sort_direction?: string | null;
    pagination_size?: number | null;
    coupon_code?: string | null;
    trial_extension?: unknown;
    free_tier_feature?: unknown;
    premium_feature?: string | null;
    pay_with_card?: boolean | null;
    name: string;
    /**
     * @metadata required-conditionally
     * @format email
     */
    notification_email: string | null;
    phone_number: string | null;
    reason_for_declining: string | null;
    /** @metadata required-conditionally */
    password_reset_token: string | null;
    /** @metadata required-conditionally */
    address_line_2: string | null;
    /** @metadata required-conditionally */
    full_address: string | null;
    /** @metadata required-conditionally */
    mobile: string | null;
    /** @metadata required-conditionally */
    contact_method: string | null;
    permissions: unknown[];
    optional_preference?: string;
    is_authenticated: boolean;
    role: string;
    display_name?: string | null;
    /** @format email */
    primary_email: string;
    environment: string;
    new_field?: string | null;
    option_a?: string | null;
    option_b?: string | null;
    has_filter: boolean;
    sort_field?: string | null;
    page?: number | null;
    is_free: boolean;
    is_paid_subscriber: boolean;
    accept_free_terms: boolean;
    plan: string;
    pay_with_bank_transfer?: boolean | null;
    send_notifications: boolean;
    send_sms: boolean;
    accept_terms: boolean;
    use_sso: boolean;
    address_line_1?: string | null;
    city?: string | null;
    postal_code?: string | null;
    landline?: string | null;
    /** @format email */
    email?: string | null;
    mobile_optional?: string | null;
}
