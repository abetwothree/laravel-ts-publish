import type { Order, Product } from './';

export interface OrderItem
{
    // Columns
    id: number;
    order_id: number;
    product_id: string;
    name: string;
    sku: string;
    quantity: number;
    unit_price: number;
    total_price: number;
    options: Record<string, string | number | boolean> | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** Line item subtotal computed from quantity × unit_price */
    subtotal: number;
    // Relations
    order: Order;
    product: Product;
    // Counts
    order_count: number;
    product_count: number;
    // Exists
    order_exists: boolean;
    product_exists: boolean;
}
