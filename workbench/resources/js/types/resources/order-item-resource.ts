import type { Order } from '../models';
import type { ProductResource } from './';

/** Exercises: whenLoaded with Resource::make, whenLoaded bare (1-arg form), whenNotNull on nullable JSON column. */
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
}
