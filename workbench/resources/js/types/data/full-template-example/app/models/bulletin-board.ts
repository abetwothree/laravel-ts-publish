import type { Bulletin, Comment, User } from '.';

/**
 * Reads Bulletin's accessors through a typed closure parameter and a nullsafe relation chain; each names its own class,
 * so the file imports `Comment` and `User` only if both reads carry the accessor's class.
 *
 * @see Workbench\App\Models\BulletinBoard
 */
export interface BulletinBoard
{
    // Columns
    id: number;
    title: string;
    content: string;
    user_id: number;
    status: boolean;
    published_at: string | null;
    metadata: string | null;
    rating: number | null;
    category: string;
    options: string | null;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    category_id: number | null;
    visibility: string | null;
    priority: number | null;
    word_count: number | null;
    reading_time_minutes: number | null;
    featured_image_url: string | null;
    is_pinned: boolean;
    // Mutators
    comment_lists: Comment[][];
    lead_author: Pick<User, 'id' | 'name'> | null;
    // Relations
    bulletins: Bulletin[];
    lead: Bulletin;
    // Counts
    bulletins_count: number;
    lead_count: number;
    // Exists
    bulletins_exists: boolean;
    lead_exists: boolean;
}
