import type { CurrencyType, OrderStatusType, PaymentMethodType } from '../../enums';
import type { OrderItem, User } from '../../models';

/** Exercises return $this->except([...]) as a direct return. */
export interface OrderExceptResource
{
    id: number;
    ulid: string;
    user_id: number;
    status: OrderStatusType;
    payment_method: PaymentMethodType | null;
    currency: CurrencyType;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    shipping_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    billing_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    notes: string | null;
    placed_at: string | null;
    paid_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    item_count: number;
    is_paid: boolean;
    formatted_total: string;
    search_index: unknown;
    user: User;
    items: OrderItem[];
}
