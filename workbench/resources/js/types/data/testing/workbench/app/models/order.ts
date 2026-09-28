import { type AsEnum } from '@tolki/ts';

import { Currency, OrderStatus, PaymentMethod } from '../enums';
import type { CurrencyType, OrderStatusType, PaymentMethodType } from '../enums';
import type { OrderItem, User } from '.';
import type { Store } from './admin';

/** @see Workbench\App\Models\Order */
export interface Order
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
    /** Trimmed notes — accessor on a nullable DB column */
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
}

export interface OrderResource extends Omit<Order, 'status' | 'payment_method' | 'currency'>
{
    status: AsEnum<typeof OrderStatus>;
    payment_method: AsEnum<typeof PaymentMethod> | null;
    currency: AsEnum<typeof Currency>;
}

export interface OrderMutators
{
    /** Number of line items in this order */
    item_count: number;
    /** Whether the order has been paid */
    is_paid: boolean;
    /** Formatted total with currency symbol */
    formatted_total: string;
    flagged_notes: (string | null)[] | null;
    /** Write-only mutator whose docblock still documents what a getter would return. */
    tracking_code: string | null;
    score_map: Record<string, number>;
    sorted_items: OrderItem[];
    keyed_items: Record<string, OrderItem>;
    listed_items: OrderItem[];
    /** All items on the order, in their natural database order. */
    unsorted_items: OrderItem[];
    state_ids: number[] | null;
    capabilities: { typeName: string; tracksSteelDetails: boolean; warehouseDocsKey: string | null } | null;
    summary_items: Store[];
}

export interface OrderRelations
{
    // Relations
    user: User;
    items: OrderItem[];
    // Counts
    user_count: number;
    items_count: number;
    // Exists
    user_exists: boolean;
    items_exists: boolean;
}

export interface OrderAll extends Order, OrderMutators, OrderRelations {}

export interface OrderAllResource extends OrderResource, OrderMutators, OrderRelations {}
