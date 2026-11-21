import type { User as CrmUser } from '../../../../crm/models';
import type { User as WorkbenchUser } from '../../../models';

/**
 * A key the `@return` does not name, holding an arm no rule types beside two same-named users (GH-161).
 *
 * @see Workbench\App\Http\Resources\DroppedArms\ArmBesideTypedKeyResource
 */
export interface ArmBesideTypedKeyResource
{
    n: number;
    users: WorkbenchUser[] | CrmUser[];
}
