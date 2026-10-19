/**
 * Exercises mergeWhen() and mergeUnless() with a default, which Laravel merges when the condition fails. A key only
 * one side sets is optional, and a key both sides set is required, typed with both sides' types.
 *
 * @see Workbench\App\Http\Resources\MergeDefaultResource
 */
export interface MergeDefaultResource
{
    id: number;
    state: string | number;
    paid_by?: number;
    awaiting_payment?: boolean;
    cancelled: boolean;
    open_since?: string | null;
    note_text: string | null;
    owner_id?: number;
    subtotal_label?: string;
    k: string;
    null_total: null;
}
