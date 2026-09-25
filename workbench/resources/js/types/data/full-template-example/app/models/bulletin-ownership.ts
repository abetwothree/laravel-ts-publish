import type { BulletinDigest, User } from '.';

/**
 * Appends an accessor its `Attribute<User, never>` docblock types, for a resource that spreads this model's toArray().
 *
 * @see Workbench\App\Models\BulletinOwnership
 */
export interface BulletinOwnership
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
    owner: User;
    // Relations
    author: User;
    digest: BulletinDigest;
    // Counts
    author_count: number;
    digest_count: number;
    // Exists
    author_exists: boolean;
    digest_exists: boolean;
}
