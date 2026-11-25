import type { User as CrmUser } from '../../../../crm/models';
import type { User as ModelsUser } from '../../../models';

/**
 * Under Laravel's `array<string, mixed>` stub, an arm no rule types beside a typed one (GH-161): `cache()` sends
 * `null` here, beside an arrow override, a list of them, and lists of two same-named users.
 *
 * @see Workbench\App\Http\Resources\DroppedArms\ArmBesideDropResource
 */
export interface ArmBesideDropResource
{
    cb: () => string;
    cbs: (() => string)[];
    users: ModelsUser[] | CrmUser[];
}
