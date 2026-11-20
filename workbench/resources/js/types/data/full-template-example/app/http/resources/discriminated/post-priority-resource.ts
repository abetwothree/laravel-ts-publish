import type { UserResource } from '..';

/**
 * A nullable enum the resource sends picks two keys: a `match` with grouped cases and a `null` arm, and a `when()`
 * whose key is present for one case only (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\PostPriorityResource
 */
export type PostPriorityResource = {
    id: number;
} & (
    | { priority: 0 | 1; badge: null; escalated_at?: never }
    | { priority: 2; badge: string; escalated_at?: never }
    | { priority: 3; badge: UserResource; escalated_at: string | null }
    | { priority: null; badge: never[]; escalated_at?: never }
);
