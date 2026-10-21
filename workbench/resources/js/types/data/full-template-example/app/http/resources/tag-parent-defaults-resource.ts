import type { Post, Product } from '../../models';

/**
 * Exercises: parent::toArray() `+` a local's defaults, as the whole return.
 *
 * @see Workbench\App\Http\Resources\TagParentDefaultsResource
 */
export interface TagParentDefaultsResource
{
    id: number;
    name: string;
    slug: string;
    color: string | null;
    created_at: string | null;
    updated_at: string | null;
    posts?: Post[];
    products?: Product[];
    note: string;
}
