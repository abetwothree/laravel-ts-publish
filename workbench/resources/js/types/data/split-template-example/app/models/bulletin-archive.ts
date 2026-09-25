import type { Bulletin, Comment, User } from '.';

/**
 * Reads Bulletin's accessors through pluck() and inside a shape a closure parameter builds.
 *
 * @see Workbench\App\Models\BulletinArchive
 */
export interface BulletinArchive
{
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
}

export interface BulletinArchiveMutators
{
    comment_lists: Comment[][];
    author_rows: ({ author: Pick<User, 'id' | 'name'> })[];
}

export interface BulletinArchiveRelations
{
    // Relations
    bulletins: Bulletin[];
    // Counts
    bulletins_count: number;
    // Exists
    bulletins_exists: boolean;
}

export interface BulletinArchiveAll extends BulletinArchive, BulletinArchiveMutators, BulletinArchiveRelations {}
