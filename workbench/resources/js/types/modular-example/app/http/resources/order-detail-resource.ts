import { type AsEnum } from '@tolki/ts';

import { OrderStatus } from '../../enums';
import type { CurrencyType } from '../../enums';
import type { OrderItem } from '../../models';
import type { UserResource } from '.';

/**
 * Exercises advanced merge patterns: mergeWhen with EnumResource::make, mergeWhen with Resource::make, whenLoaded with value arg.
 *
 * @see Workbench\App\Http\Resources\OrderDetailResource
 */
export interface OrderDetailResource
{
    id: number;
    status: AsEnum<typeof OrderStatus>;
    user?: UserResource;
    payment_status?: AsEnum<typeof OrderStatus>;
    payment_currency?: CurrencyType;
    shipping_user?: UserResource;
    order_items?: OrderItem[];
}
