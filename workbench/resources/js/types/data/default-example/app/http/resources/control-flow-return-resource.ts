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
    draft?: boolean;
    total?: number;
    status?: OrderStatusType;
}
