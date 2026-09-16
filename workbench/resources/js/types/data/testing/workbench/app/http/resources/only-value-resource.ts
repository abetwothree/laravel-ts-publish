import type { Category, Post } from '../../models';

/**
 * only() away from a relation receiver: a top-level spread that names a withCount() virtual the schema
 * lacks, and two value-position calls — on $this (forwarded to the model) and on a whenLoaded closure
 * parameter — which must reference the model the receiver holds rather than only()'s vague array return.
 *
 * The last two keys are the counter-case: with no literal key list there is nothing for the receiver rule
 * to Pick<>, so they must keep the vague shape their old claimant reflects instead of degrading to unknown.
 *
 * @see Workbench\App\Http\Resources\OnlyValueResource
 */
export interface OnlyValueResource
{
    id: number;
    comments_count: number;
    summary?: Pick<Post, 'id' | 'title'>;
    category?: Pick<Category, 'id' | 'name'>;
    dynamic: Record<string, unknown>;
    dynamic_category?: Record<string, unknown>;
}
