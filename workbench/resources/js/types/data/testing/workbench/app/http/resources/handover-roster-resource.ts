import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * Reads members typed by a docblock union whose arms render alike for two models that share a name.
 *
 * @see Workbench\App\Http\Resources\HandoverRosterResource
 */
export interface HandoverRosterResource
{
    id: number;
    members: WorkbenchUser[] | CrmUser[];
    reviewers: WorkbenchUser[] | CrmUser[];
    involved: WorkbenchUser[] | CrmUser[] | WorkbenchUser | null;
}
