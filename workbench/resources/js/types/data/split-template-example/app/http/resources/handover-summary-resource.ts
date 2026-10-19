import type { User as CrmUser } from '../../../crm/models';
import type { User as ModelsUser } from '../../models';

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
    parties: { first: ModelsUser | null; either: ModelsUser | CrmUser | null };
    sender?: ModelsUser | null;
    receiver?: CrmUser | null;
    watchers?: ModelsUser[];
    crm_watchers?: CrmUser[];
}
