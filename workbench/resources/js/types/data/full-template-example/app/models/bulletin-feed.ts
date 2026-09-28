import type { Bulletin, Comment, User } from '.';

/**
 * Reads Bulletin's accessors through a relation chain and an untyped closure parameter.
 *
 * @see Workbench\App\Models\BulletinFeed
 */
export interface BulletinFeed
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
    lead_comments: Comment[];
    /** Bulletin's owner is typed by its `Attribute<User, never>` docblock. */
    owners: User[];
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
