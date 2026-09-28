import type { Post, User } from '.';

/** @see Workbench\App\Models\Comment */
export interface Comment
{
    // Columns
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
    // Mutators
    /** Short preview of the comment */
    preview: string;
    /** The same relation filters, read as a getter body. */
    relation_picks: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] };
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
