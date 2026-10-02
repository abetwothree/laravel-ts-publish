import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * Declares no toArray(), so it publishes the model's own serialization, appended `parties` accessor included: the
 * union of two same-named models inside it has to survive the delegation with each token naming its own class.
 *
 * @see Workbench\App\Http\Resources\HandoverSummaryResource
 */
export interface HandoverSummaryResource
{
    id: number;
    sender_id: number | null;
    receiver_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    party: WorkbenchUser | CrmUser | null;
    parties: { first: WorkbenchUser | null; either: WorkbenchUser | CrmUser | null };
    audience: WorkbenchUser[] | CrmUser[];
    sender: WorkbenchUser | null;
    receiver: CrmUser | null;
    watchers: WorkbenchUser[];
    crmWatchers: CrmUser[];
}
