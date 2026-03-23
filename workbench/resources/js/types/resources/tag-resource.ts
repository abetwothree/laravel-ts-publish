import type { PostResource, ProductResource } from './';

/** Exercises: whenCounted on two polymorphic relations. */
export interface TagResource
{
    id: number;
    name: string;
    slug: string;
    color: string | null;
    posts_count?: number;
    products_count?: number;
    posts?: PostResource[];
    products?: ProductResource[];
}
