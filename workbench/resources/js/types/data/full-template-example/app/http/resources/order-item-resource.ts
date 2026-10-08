import type { Order } from '../../models';
import type { ProductResource } from '.';

/**
 * Exercises: whenLoaded with Resource::make, whenLoaded bare (1-arg form),
 * whenNotNull on nullable JSON column.
 *
 * @see Workbench\App\Http\Resources\OrderItemResource
 */
export interface OrderItemResource
{
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price: string;
    total_price: string;
    product?: ProductResource;
    order?: Order;
    options?: Record<string, string | number | boolean> | null;
    order_limited: Pick<Order, 'id' | 'total'> | null;
    order_extended: Pick<Order, 'id' | 'ulid' | 'user_id' | 'status' | 'payment_method' | 'currency' | 'subtotal' | 'tax' | 'discount' | 'total' | 'shipping_address' | 'billing_address' | 'notes' | 'placed_at' | 'paid_at' | 'shipped_at' | 'delivered_at' | 'cancelled_at' | 'ip_address' | 'user_agent' | 'deleted_at'>;
}
