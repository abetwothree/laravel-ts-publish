import { StatusType } from '../enums';
import { User } from './';

export interface Post
{
    id: number;
    title: string;
    content: string;
    user_id: number;
    status: StatusType;
    published_at: string | null;
    metadata: Record<string, {title: string, content: string}>;
    rating: number | null;
    category: string;
    options: Array<unknown> | null;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface PostMutators
{
    title_display: string | null;
}

export interface PostRelations
{
    author: User;
}

export interface PostRelationCounts
{
    author_count: number;
}

export interface PostRelationExists
{
    author_exists: boolean;
}
