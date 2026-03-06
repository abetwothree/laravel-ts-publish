import { Post, Product } from './';

export interface Tag
{
    id: number;
    name: string;
    slug: string;
    color: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface TagRelations
{
    // Relations
    posts: Post[];
    products: Product[];
    // Counts
    posts_count: number;
    products_count: number;
    // Exists
    posts_exists: boolean;
    products_exists: boolean;
}

export interface TagAll extends Tag, TagRelations {}
