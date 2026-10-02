import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * Reads two models that share a name through the conditional helpers: each arm keeps its own class.
 *
 * @see Workbench\App\Http\Resources\HandoverNoticeResource
 */
export interface HandoverNoticeResource
{
    id: number;
    counterparty: CrmUser | WorkbenchUser | null;
    unclaimed: CrmUser | null;
}
