import type { User as CrmUser } from '../../../crm/models';
import type { User as ModelsUser } from '../../models';

/**
 * Unions two models that share a name by `??`, a ternary and an inline array, and reads an accessor that does the same.
 *
 * @see Workbench\App\Http\Resources\HandoverResource
 */
export interface HandoverResource
{
    id: number;
    party: ModelsUser | CrmUser | null;
    picked: CrmUser | ModelsUser | null;
    pair: { first: ModelsUser | null; either: ModelsUser | CrmUser | null };
    parties: { first: ModelsUser | null; either: ModelsUser | CrmUser | null };
    audience: ModelsUser[] | CrmUser[];
}
