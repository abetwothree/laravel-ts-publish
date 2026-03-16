import type { Post } from '.';

export interface Category
{
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    sort_order: number;
    is_active: boolean;
    metadata: Record<string, string | number>;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

export interface CategoryMutators
{
    /** Full path breadcrumb (e.g. "Electronics > Phones > Smartphones") */
    breadcrumb: string;
}

export interface CategoryRelations
{
    // Relations
    /** Self-referencing: parent category */
    parent: Category | null;
    /** Self-referencing: child categories */
    children: Category[];
    /** Posts in this category */
    posts: Post[];
    // Counts
    parent_count: number;
    children_count: number;
    posts_count: number;
    // Exists
    parent_exists: boolean;
    children_exists: boolean;
    posts_exists: boolean;
}

export interface CategoryAll extends Category, CategoryMutators, CategoryRelations {}
