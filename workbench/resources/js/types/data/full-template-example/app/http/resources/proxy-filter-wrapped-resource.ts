import type { Comment, Post, User } from '../../models';

/**
 * ProxyFilterDirectResource with every only() and except() spelled through $this->resource. The resource
 * forwards to the same model either way, so the two must publish the same shape.
 *
 * @see Workbench\App\Http\Resources\ProxyFilterWrappedResource
 */
export interface ProxyFilterWrappedResource
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
