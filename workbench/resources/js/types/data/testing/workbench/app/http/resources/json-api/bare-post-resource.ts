import type { PriorityType, StatusType, VisibilityType } from '../../../enums';
import type { Attachment, Category, Comment, Image, Tag, User } from '../../../models';

/**
 * A JSON:API resource naming no attributes, so toAttributes() falls back to the model's own toArray().
 *
 * @see Workbench\App\Http\Resources\JsonApi\BarePostResource
 */
export interface BarePostResource
{
    id: string;
    type: 'bare_posts';
    attributes: { id: number; title: string; content: string; user_id: number; status: StatusType; published_at: string | null; metadata: Record<string, {title: string, content: string}>; rating: string | null; category: string; options: Record<string, string> | null; deleted_at: string | null; created_at: string | null; updated_at: string | null; category_id: number | null; visibility: VisibilityType | null; priority: PriorityType | null; word_count: number | null; reading_time_minutes: number | null; featured_image_url: string | null; is_pinned: boolean; author?: User; category_rel?: Category | null; comments?: Comment[]; tags?: Tag[]; images?: Image[]; attachment?: Attachment | null };
}
