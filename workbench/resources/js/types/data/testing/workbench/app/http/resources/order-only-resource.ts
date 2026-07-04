import type { OrderStatusType } from '../../enums';
import type { OrderItem } from '../../models';
import type { UserResource } from '.';

/**
 * Exercises ...$this->only([...]) spread with additional manual keys.
 *
 * @see Workbench\App\Http\Resources\OrderOnlyResource
 */
export interface OrderOnlyResource
{
    id: number;
    status: OrderStatusType;
    total: number;
    notes: string | null;
    item_count: number;
    items: OrderItem[];
    user?: UserResource;
}
