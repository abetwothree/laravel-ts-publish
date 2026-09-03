import type { Post, User } from '.';

/** @see Workbench\App\Models\Comment */
export interface Comment
{
    id: number;
    content: string;
    post_id: number;
    user_id: number;
    parent_id: number | null;
    is_flagged: boolean;
    flagged_at: string | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
}

export interface CommentMutators
{
    /** Short preview of the comment */
    preview: string;
}

export interface CommentRelations
{
    // Relations
    post: Post;
    user: User;
    /** Self-referencing: replies to this comment */
    replies: Comment[];
    // Counts
    post_count: number;
    user_count: number;
    replies_count: number;
    // Exists
    post_exists: boolean;
    user_exists: boolean;
    replies_exists: boolean;
}

export interface CommentAll extends Comment, CommentMutators, CommentRelations {}
