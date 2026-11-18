import type { PriorityType } from '../../../enums';
import type { UserResource } from '..';

/**
 * A nullable enum the resource sends picks two keys: a `match` with grouped cases and a `null` arm, and a `when()`
 * whose key is present for one case only (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\PostPriorityResource
 */
export interface PostPriorityResource
{
    id: number;
    priority: PriorityType | null;
    badge: string | UserResource | never[] | null;
    escalated_at?: string | null;
}
