import type { OrderResource } from '.';

export interface OrderCollection
{
    data: OrderResource[];
    total_count: unknown;
}
