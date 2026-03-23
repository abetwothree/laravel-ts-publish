import { type AsEnum } from '@tolki/enum';

import { Currency, OrderStatus } from '../enums';
import type { OrderItem } from '../models';

export interface OrderResource
{
    id: number;
    status: AsEnum<typeof OrderStatus>;
    total: number;
    currency: AsEnum<typeof Currency>;
    items?: OrderItem[];
    items_count?: number;
    total_avg?: number;
    paid_at?: string;
    shipped_at?: string;
    delivered_at?: string;
}
