import type { OrderStatusType } from '../../enums';
import type { User } from '../../models';

/**
 * Exercises direct property access for accessors, mutators, and relations without using whenLoaded or other conditional wrappers.
 *
 * @see Workbench\App\Http\Resources\OrderSummaryResource
 */
export interface OrderSummaryResource
{
    id: number;
    is_paid: boolean;
    item_count: number;
    formatted_total: string;
    user: User;
    status: OrderStatusType;
    total: number;
    notes: string | null;
    search_index: unknown;
}
