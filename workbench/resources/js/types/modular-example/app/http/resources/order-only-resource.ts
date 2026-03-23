import type { OrderStatusType } from '../../enums';
import type { UserResource } from '.';

/** Exercises ...$this->only([...]) spread with additional manual keys. */
export interface OrderOnlyResource
{
    id: number;
    status: OrderStatusType;
    total: number;
    user?: UserResource;
}
