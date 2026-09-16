import type { Post, User } from '../../models';

/**
 * only() and except() written against $this, in spread and value position, on the resource's own model and
 * on a single-model relation. ProxyFilterWrappedResource spells every call through $this->resource and must
 * publish exactly this shape.
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
}
