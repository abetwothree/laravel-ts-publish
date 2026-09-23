import { type AsEnum } from '@tolki/ts';

import { Currency, OrderStatus } from '../../enums';
import type { OrderItem } from '../../models';

/**
 * Declares neither a toArray() nor a @mixin — pins parent-docblock model inheritance: the model
 * has to come from OrderResource's own `@mixin Order`, since the naming convention would look for
 * a Workbench\App\Models\BodylessOrder that does not exist. Without it every column is unknown.
 *
 * @see Workbench\App\Http\Resources\BodylessOrderResource
 */
export interface BodylessOrderResource
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
