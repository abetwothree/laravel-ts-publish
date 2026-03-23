import type { CurrencyType, OrderStatusType, PaymentMethodType } from '../enums';

/** Exercises return $this->except([...]) as a direct return. */
export interface OrderExceptResource
{
    id: number;
    ulid: string;
    user_id: number;
    status: OrderStatusType;
    payment_method: PaymentMethodType;
    currency: CurrencyType;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    shipping_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    billing_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    notes: string | null;
    placed_at: string;
    paid_at: string;
    shipped_at: string;
    delivered_at: string;
    cancelled_at: string;
    created_at: string;
    updated_at: string;
    deleted_at: string;
    item_count: number;
    is_paid: boolean;
    formatted_total: string;
    search_index: unknown;
}
