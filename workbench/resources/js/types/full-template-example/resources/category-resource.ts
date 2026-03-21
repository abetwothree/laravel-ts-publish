import type { PostResource } from './';

/** Exercises: self-referencing Resource::make and Resource::collection, when conditional, whenCounted, cross-resource PostResource::collection. */
export interface CategoryResource
{
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
    parent?: CategoryResource;
    children?: CategoryResource[];
    posts?: PostResource[];
    posts_count?: number;
}
