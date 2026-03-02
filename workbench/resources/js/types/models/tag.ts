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
    posts: Post;
    products: Product;
}

export interface TagRelationCounts
{
    posts_count: number;
    products_count: number;
}

export interface TagRelationExists
{
    posts_exists: boolean;
    products_exists: boolean;
}
