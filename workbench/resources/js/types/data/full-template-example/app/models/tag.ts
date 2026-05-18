import type { Post, Product } from '.';

/**
 * @see Workbench\App\Models\Tag
 */
export interface Tag
{
    // Columns
    id: number;
    name: string;
    slug: string;
    color: string | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** Posts with this tag (polymorphic many-to-many) */
    posts: Post[];
    /** Products with this tag (polymorphic many-to-many) */
    products: Product[];
    // Counts
    posts_count: number;
    products_count: number;
    // Exists
    posts_exists: boolean;
    products_exists: boolean;
}
