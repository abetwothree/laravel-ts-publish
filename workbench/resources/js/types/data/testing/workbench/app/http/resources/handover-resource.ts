import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * Unions two models that share a name by `??`, a ternary and an inline array, and reads an accessor that does the same.
 *
 * @see Workbench\App\Http\Resources\HandoverResource
 */
export interface HandoverResource
{
    id: number;
    party: WorkbenchUser | CrmUser | null;
    picked: CrmUser | WorkbenchUser | null;
    pair: { first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null };
    parties: { first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null };
    audience: WorkbenchUser[] | CrmUser[];
}
