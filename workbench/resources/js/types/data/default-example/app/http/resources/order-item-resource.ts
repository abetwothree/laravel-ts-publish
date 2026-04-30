import type { CurrencyType, OrderStatusType, PaymentMethodType } from '../../enums';
import type { Order, OrderItem, User } from '../../models';
import type { ProductResource } from '.';

/**
 * Exercises: whenLoaded with Resource::make, whenLoaded bare (1-arg form), whenNotNull on nullable JSON column.
 *
 * @see Workbench\App\Http\Resources\OrderItemResource
 */
export interface OrderItemResource
{
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price: number;
    total_price: number;
    product?: ProductResource;
    order?: Order;
    options?: Record<string, string | number | boolean> | null;
    order_limited: { id: number; total: number } | null;
    order_extended: { id: number; ulid: string; user_id: number; status: OrderStatusType; payment_method: PaymentMethodType | null; currency: CurrencyType; subtotal: number; tax: number; discount: number; total: number; shipping_address: unknown[] | null; billing_address: unknown[] | null; notes: string | null; placed_at: string | null; paid_at: string | null; shipped_at: string | null; delivered_at: string | null; cancelled_at: string | null; ip_address: string | null; user_agent: string | null; deleted_at: string | null; item_count: number; is_paid: boolean; formatted_total: string; user: User; items: OrderItem[] };
}
