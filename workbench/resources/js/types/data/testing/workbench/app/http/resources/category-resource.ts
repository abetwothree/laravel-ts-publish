import type { Category } from '../../models';
import type { PostResource } from '.';

/**
 * Exercises: self-referencing Resource::make and Resource::collection,
 * when conditional, whenCounted, cross-resource PostResource::collection.
 *
 * @see Workbench\App\Http\Resources\CategoryResource
 */
export interface CategoryResource
{
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    parent?: CategoryResource | null;
    children?: CategoryResource[];
    posts?: PostResource[];
    posts_count?: number;
    children_self_collection: CategoryResource[];
    children_self_resource_collection: CategoryResource[];
    children_self_collection_first_callable: CategoryResource[];
    children_when_self_collection?: CategoryResource[];
    children_when_self_resource_collection?: CategoryResource[];
    children_when_self_collection_first_callable?: CategoryResource[];
    parent_self: CategoryResource | null;
    parent_make_self: CategoryResource | null;
    parent_resource_self: CategoryResource | null;
    parent_when_self?: CategoryResource | null;
    parent_when_resource_self?: CategoryResource | null;
    children_with_default: Category[];
    posts_with_default: PostResource[];
}
