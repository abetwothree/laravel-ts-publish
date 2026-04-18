import type { CurrencyType, OrderStatusType } from '../../enums';
import type { OrderItemResource, UserResource } from '.';

/**
 * Exercises closure / arrow function patterns in value expressions and merge methods.
 *
 * @see Workbench\App\Http\Resources\OrderClosureResource
 */
export interface OrderClosureResource
{
    id: number;
    status_arrow?: OrderStatusType;
    user_arrow?: UserResource;
    items_arrow?: OrderItemResource[];
    notes_closure?: string | null;
    shipped_at?: string | null;
    tracking?: string | null;
    currency_label: CurrencyType;
    total_display: number;
}
