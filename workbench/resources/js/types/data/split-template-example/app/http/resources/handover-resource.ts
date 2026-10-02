import type { User as CrmUser } from '../../../crm/models';
import type { User as ModelsUser } from '../../models';

/**
 * Unions two models that share a name, by `??`, by a ternary and inside an inline array.
 *
 * @see Workbench\App\Http\Resources\HandoverResource
 */
export interface HandoverResource
{
    id: number;
    party: ModelsUser | CrmUser | null;
    picked: CrmUser | ModelsUser | null;
    pair: { first: ModelsUser | null; either: ModelsUser | CrmUser | null };
    audience: ModelsUser[] | CrmUser[];
}
