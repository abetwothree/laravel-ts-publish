import { CurrencyType, OrderStatusType, PaymentMethodType } from '../enums';
import { OrderItem, User } from './';

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

export interface OrderMutators
{
    item_count: number;
    is_paid: boolean;
    formatted_total: string;
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
