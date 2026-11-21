import type { User as CrmUser } from '../../../../crm/models';
import type { User as WorkbenchUser } from '../../../models';

/**
 * The same-named users of ArmBesideDropResource joined by a returned variable's branches (GH-161).
 *
 * @see Workbench\App\Http\Resources\DroppedArms\WalkedArmDropResource
 */
export interface WalkedArmDropResource
{
    users: WorkbenchUser[] | CrmUser[];
}
