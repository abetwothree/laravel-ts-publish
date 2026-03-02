import { Order, Product } from './';

export interface OrderItem
{
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
}

export interface OrderItemMutators
{
    subtotal: number;
}

export interface OrderItemRelations
{
    order: Order;
    product: Product;
}

export interface OrderItemRelationCounts
{
    order_count: number;
    product_count: number;
}

export interface OrderItemRelationExists
{
    order_exists: boolean;
    product_exists: boolean;
}
