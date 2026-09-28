import type { BulletinOwnership, Comment, User } from '.';

/**
 * Appends accessors whose types name a class, so a resource that spreads this model's toArray() publishes them:
 * a list of `Comment`, a `Pick<User, …>` and a `Pick<BulletinDigest, …>`.
 *
 * @see Workbench\App\Models\BulletinDigest
 */
export interface BulletinDigest
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
    comment_list: Comment[];
    author_pick: Pick<User, 'id' | 'name'>;
    own_pick: Pick<BulletinDigest, 'id' | 'title'>;
}

export interface BulletinDigestRelations
{
    // Relations
    comments: Comment[];
    author: User;
    ownership: BulletinOwnership;
    // Counts
    comments_count: number;
    author_count: number;
    ownership_count: number;
    // Exists
    comments_exists: boolean;
    author_exists: boolean;
    ownership_exists: boolean;
}

export interface BulletinDigestAll extends BulletinDigest, BulletinDigestRelations {}
