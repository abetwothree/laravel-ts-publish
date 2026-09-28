import type { BulletinDigest, Comment, User } from '../../models';

/**
 * Spreads a related BulletinDigest's toArray(), whose appended accessors name `Comment`, `User` and `BulletinDigest`.
 * BulletinOwnership declares none of those accessors, so no name lookup imports them.
 *
 * @see Workbench\App\Http\Resources\BulletinDigestSpreadResource
 */
export interface BulletinDigestSpreadResource
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
