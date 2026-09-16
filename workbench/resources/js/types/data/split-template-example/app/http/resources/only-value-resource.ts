import type { Category, Post } from '../../models';

/**
 * only() away from a relation receiver: a top-level spread that names a withCount() virtual the schema
 * lacks, and two value-position calls — on $this (forwarded to the model) and on a whenLoaded closure
 * parameter — which must reference the model the receiver holds rather than only()'s vague array return.
 *
 * The last two keys are the counter-case: with no literal key list there is nothing to Pick<>, so the receiver rule
 * answers both with Record<string, unknown>, the attribute-keyed array only() returns, instead of unknown.
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
