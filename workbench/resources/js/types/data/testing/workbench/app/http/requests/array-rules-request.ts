/**
 * Get the validation rules that apply to the request.
 *
 * @see Workbench\App\Http\Requests\ArrayRulesRequest
 */
export interface ArrayRulesRequest {
    tags?: string[];
    "tags.*"?: string;
    selected_ids: number[];
    "selected_ids.*"?: number;
    roles: string[];
    "roles.*"?: string;
    allowed_roles: string[];
    "allowed_roles.*"?: string;
    sku_codes: string[];
    "sku_codes.*"?: string;
    airports: string[];
    "airports.*"?: string;
    primary_airport: string;
    config: unknown[];
    ordered_items: string[];
    "ordered_items.*"?: string;
    limited_choices?: string[] | null;
    "limited_choices.*"?: string | null;
    required_answers: string[];
    "required_answers.*"?: string;
    coordinates: number[];
    "coordinates.*"?: number;
    products: unknown[];
    "products.*.name"?: string;
    "products.*.price"?: number;
    "products.*.quantity"?: number;
    "products.*.categories"?: string[];
    "products.*.categories.*"?: string;
    "products.*.is_available"?: boolean;
    order: unknown[];
    /** @format uuid */
    "order.id"?: string;
    "order.items"?: unknown[];
    "order.items.*.product_id"?: number;
    "order.items.*.quantity"?: number;
}
