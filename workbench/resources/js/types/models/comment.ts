import { Post, User } from './';

export interface Comment
{
    id: number;
    content: string;
    post_id: number;
    user_id: number;
    is_flagged: boolean;
    flagged_at: string | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
}

export interface CommentMutators
{
    preview: string;
}

export interface CommentRelations
{
    post: Post;
    user: User;
}

export interface CommentRelationCounts
{
    post_count: number;
    user_count: number;
}

export interface CommentRelationExists
{
    post_exists: boolean;
    user_exists: boolean;
}
