import type { Category, Post } from '../../models';

/**
 * only() away from a relation receiver: a top-level spread that names a withCount() virtual the schema
 * lacks, and two value-position calls — on $this (forwarded to the model) and on a whenLoaded closure
 * parameter — which must reference the model the receiver holds rather than only()'s vague array return.
 *
 * @see Workbench\App\Http\Resources\OnlyValueResource
 */
export interface OnlyValueResource
{
    id: number;
    comments_count: number;
    summary?: Pick<Post, 'id' | 'title'>;
    category?: Pick<Category, 'id' | 'name'>;
}
