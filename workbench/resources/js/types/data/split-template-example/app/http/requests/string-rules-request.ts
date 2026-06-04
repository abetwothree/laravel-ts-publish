/**
 * Get the validation rules that apply to the request.
 *
 * @see Workbench\App\Http\Requests\StringRulesRequest
 */
export interface StringRulesRequest {
    /** @format active_url */
    website: string;
    first_name: string;
    username: string;
    reference_code: string;
    ascii_id: string;
    password: string;
    old_password: string;
    new_password: string;
    slug: string;
    path: string;
    /** @format email */
    email: string;
    status_suffix: string;
    media_type: 'image' | 'video' | 'audio' | 'document' | 'archive';
    /** @format hex_color */
    brand_color: string;
    country_code: 'US' | 'CA' | 'UK' | 'AU';
    /** @format ip */
    ip_address?: string | null;
    /** @format ipv4 */
    ipv4_address?: string | null;
    /** @format ipv6 */
    ipv6_address?: string | null;
    metadata?: string | null;
    locale_code: string;
    /** @format mac_address */
    device_mac?: string | null;
    title: string;
    bio: string;
    topping: string;
    postal_code: string;
    clean_text: string;
    /** @format email */
    email_confirm: string;
    iso_country_code: string;
    honorific: string;
    description: string;
    currency_code: string;
    /** @format url */
    homepage?: string | null;
    /** @format ulid */
    external_id?: string | null;
    /** @format uuid */
    request_id?: string | null;
}
