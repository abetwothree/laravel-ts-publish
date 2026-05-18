import { type AsEnum } from '@tolki/ts';

import { Currency, OrderStatus } from '../../enums';
import type { OrderItem } from '../../models';

/**
 * @see Workbench\App\Http\Resources\OrderResource
 */
export interface OrderResource
{
    id: number;
    status: AsEnum<typeof OrderStatus>;
    total: number;
    currency: AsEnum<typeof Currency>;
    items?: OrderItem[];
    items_count?: number;
    total_avg?: number;
    paid_at?: string | null;
    shipped_at?: string | null;
    delivered_at?: string | null;
}
