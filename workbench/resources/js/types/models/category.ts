import { Post } from './';

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
    breadcrumb: string;
}

export interface CategoryRelations
{
    parent: Category;
    children: Category[];
    posts: Post[];
}

export interface CategoryRelationCounts
{
    parent_count: number;
    children_count: number;
    posts_count: number;
}

export interface CategoryRelationExists
{
    parent_exists: boolean;
    children_exists: boolean;
    posts_exists: boolean;
}
