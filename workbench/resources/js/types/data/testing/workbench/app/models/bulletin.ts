import type { Comment, User } from '.';

/**
 * Accessors whose types name a class, read by other models and resources through a closure parameter, a relation
 * chain or pluck(). `Comment` shares its name with a DOM global, so a missing import still compiles against the DOM.
 *
 * @see Workbench\App\Models\Bulletin
 */
export interface Bulletin
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

export interface BulletinMutators
{
    /** A to-many relation's filter: a list of `Comment`. */
    comment_list: Comment[];
    /** A single relation's filter: a `Pick<User, …>`. */
    author_pick: Pick<User, 'id' | 'name'>;
    /** A filter on the model itself: a `Pick<Bulletin, …>`. */
    own_pick: Pick<Bulletin, 'id' | 'title'>;
    owner: User;
}

export interface BulletinRelations
{
    // Relations
    comments: Comment[];
    author: User;
    // Counts
    comments_count: number;
    author_count: number;
    // Exists
    comments_exists: boolean;
    author_exists: boolean;
}

export interface BulletinAll extends Bulletin, BulletinMutators, BulletinRelations {}
