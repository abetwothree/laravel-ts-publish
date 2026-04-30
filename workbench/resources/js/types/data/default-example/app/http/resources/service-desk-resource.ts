import type { User as CrmUser } from '../../../crm/models';
import type { User as AppUser } from '../../models';

/**
 * Exercises the inline model FQCN collision scenario. Two relations point to classes with the same basename: Crm\Models\User (direct, via crm_agent) and App\Models\User (embedded inside the inline object from order->only). The transformer must alias both and rewrite the token inside the inline object type string via the inlineModelFqcns tracking path.
 *
 * @see Workbench\App\Http\Resources\ServiceDeskResource
 */
export interface ServiceDeskResource
{
    id: number;
    title: string;
    crm_agent: CrmUser | null;
    order_requester: { user: AppUser } | null;
}
