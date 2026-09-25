import type { Comment, Post, User } from '../../models';

/**
 * only() and except() written against $this, in spread and value position, on the resource's own model, a
 * single-model relation and a many-relation, with literal and runtime key lists. ProxyFilterWrappedResource
 * spells every call through $this->resource and must publish exactly this shape.
 *
 * A many-relation filter keeps whole models by primary key, so it publishes the relation's own list type.
 *
 * @see Workbench\App\Http\Resources\ProxyFilterDirectResource
 */
export interface ProxyFilterDirectResource
{
    id: number;
    title: string;
    summary: Pick<Post, 'id' | 'title'>;
    without_body: Pick<Post, 'id' | 'title' | 'user_id' | 'status' | 'published_at' | 'rating' | 'category' | 'deleted_at' | 'created_at' | 'updated_at' | 'category_id' | 'visibility' | 'priority' | 'word_count' | 'reading_time_minutes' | 'featured_image_url' | 'is_pinned'>;
    author_brief: Pick<User, 'id' | 'name'>;
    author_rest: Pick<User, 'id' | 'name' | 'email_verified_at' | 'password' | 'options' | 'remember_token' | 'created_at' | 'updated_at' | 'role' | 'membership_level' | 'phone' | 'avatar' | 'bio' | 'settings' | 'last_login_at' | 'last_login_ip'>;
    author_maybe: Pick<User, 'id' | 'name'> | null;
    fields_own: Record<string, unknown>;
    fields_author: Record<string, unknown>;
    except_own: Record<string, unknown>;
    except_author: Record<string, unknown>;
    comments_by_key: Comment[];
    comments_listed: Comment[];
    comments_by_ids: Comment[];
    comments_maybe: Comment[] | null;
    comments_mapped: { id: number; content: string }[];
}
