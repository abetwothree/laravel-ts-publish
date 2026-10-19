import { type AsEnum } from '@tolki/ts';

import { PaymentMethod } from '../../enums';
import type { OrderStatusType } from '../../enums';

/**
 * Exercises collectDirectReturns elseif, else, and loop branches
 * in the main toArray() body (not inside closures).
 *
 * @see Workbench\App\Http\Resources\ControlFlowReturnResource
 */
export interface ControlFlowReturnResource
{
    id: number;
    archived?: boolean;
    inline_enum_branch?: { method: AsEnum<typeof PaymentMethod> | null };
    draft?: boolean;
    total?: string;
    status?: OrderStatusType;
}
