import type { User as CrmUser } from '../../crm/models';
import type { User as ModelsUser } from '.';

/**
 * Passes work between two models that share a name: an application User sends and a CRM User receives, so a union
 * of the two spells `User` twice and each occurrence has to name its own class.
 *
 * @see Workbench\App\Models\Handover
 */
export interface Handover
{
    id: number;
    sender_id: number | null;
    receiver_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface HandoverMutators
{
    /** Whichever party is set: a union of two models that share a name. */
    party: ModelsUser | CrmUser | null;
    /** The same union over to-many relations, picked by a ternary. */
    audience: ModelsUser[] | CrmUser[];
}

export interface HandoverRelations
{
    // Relations
    /** The application user handing the work over. */
    sender: ModelsUser | null;
    /** The CRM user taking the work on. */
    receiver: CrmUser | null;
    /** The application users watching the handover. */
    watchers: ModelsUser[];
    /** The CRM users watching the handover. */
    crm_watchers: CrmUser[];
    // Counts
    sender_count: number;
    receiver_count: number;
    watchers_count: number;
    crm_watchers_count: number;
    // Exists
    sender_exists: boolean;
    receiver_exists: boolean;
    watchers_exists: boolean;
    crm_watchers_exists: boolean;
}

export interface HandoverAll extends Handover, HandoverMutators, HandoverRelations {}
