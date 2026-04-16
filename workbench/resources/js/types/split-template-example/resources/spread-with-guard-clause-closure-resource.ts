import type { CurrencyType, OrderStatusType, PaymentMethodType, RoleType } from '../enums';
import type { OrderItem, User } from '../models';

/** Exercises both bugs simultaneously — the exact pattern from the original ProcessProcessablesResource that triggered the issue: Bug 1: ...parent::toArray() spread (2 items in outer return) + a whenLoaded closure with more items (5), causing findBestArrayReturn() to pick the wrong return statement. Bug 2: The closure has a guard clause (`return null;`) before the data array, causing resolveClosureReturnExpression() to pick null instead of the data shape. */
export interface SpreadWithGuardClauseClosureResource
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
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    item_count: number;
    is_paid: boolean;
    formatted_total: string;
    search_index: unknown;
    user: User;
    items: OrderItem[];
    customer?: { name: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean; name_titled: string; morph: string } | null;
}
