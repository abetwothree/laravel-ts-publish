import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * Unions two models that share a name, by `??`, by a ternary and inside an inline array.
 *
 * @see Workbench\App\Http\Resources\HandoverResource
 */
export interface HandoverResource
{
    id: number;
    party: WorkbenchUser | CrmUser | null;
    picked: CrmUser | WorkbenchUser | null;
    pair: { first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null };
    audience: WorkbenchUser[] | CrmUser[];
}
