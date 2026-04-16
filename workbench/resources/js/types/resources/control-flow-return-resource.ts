import type { OrderStatusType } from '../enums';

/** Exercises collectDirectReturns elseif, else, and loop branches in the main toArray() body (not inside closures). */
export interface ControlFlowReturnResource
{
    id: number;
    archived?: unknown;
    draft?: unknown;
    total?: number;
    status?: OrderStatusType;
}
