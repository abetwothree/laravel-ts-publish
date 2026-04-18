import type { OrderResource } from '.';

/**
 * @see Workbench\App\Http\Resources\OrderCollection
 */
export interface OrderCollection
{
    data: OrderResource[];
    total_count: unknown;
}
