import type { User as CrmUser } from '../../../crm/models';
import type { User as ModelsUser } from '../../models';

/**
 * Reads members typed by a docblock union whose arms render alike for two models that share a name.
 *
 * @see Workbench\App\Http\Resources\HandoverRosterResource
 */
export interface HandoverRosterResource
{
    id: number;
    members: ModelsUser[] | CrmUser[];
    reviewers: ModelsUser[] | CrmUser[];
    involved: ModelsUser[] | CrmUser[] | ModelsUser | null;
}
