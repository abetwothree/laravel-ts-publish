import type { User as CrmUser } from '../../../crm/models';
import type { User as WorkbenchUser } from '../../models';

/**
 * The model-side twin of DealEnumTrioResource: two User models sharing a basename, read across a
 * ternary so mergeUnion() builds the queue aliasPropertyType() consumes positionally. A ternary is
 * required — an inline array alone never reaches mergeUnion().
 *
 * trio pins the embedded channel: three reads, three rendered tokens, so the queue must keep its
 * repeat. collapsed_arms pins the branch-level channel against it: its two inner arms are the same
 * class, so analyzeClosureUnion() folds them to one rendered token and the queue must drop the
 * repeat — deduping both channels breaks trio, deduping neither breaks collapsed_arms.
 *
 * reversed_arms and control_arms are one expression with its arms swapped, pinning that a branch-level
 * FQCN keeps its loop position in the queue. Prepending it queues [Crm, App] for both orientations, so
 * reversed_arms silently exchanges the two User identities while control_arms stays right.
 *
 * @see Workbench\App\Http\Resources\SameBasenameModelTrioResource
 */
export interface SameBasenameModelTrioResource
{
    id: number;
    trio: { a: CrmUser | null } | { b: WorkbenchUser | null; c: CrmUser | null };
    collapsed_arms: CrmUser | { c: WorkbenchUser | null } | null;
    reversed_arms: { c: WorkbenchUser | null } | CrmUser | null;
    control_arms: CrmUser | { c: WorkbenchUser | null } | null;
}
