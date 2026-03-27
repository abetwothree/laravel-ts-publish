import type { PriorityType, StatusType, VisibilityType } from '../../enums';
import type { Category, Comment, Image, Tag, User } from '../../models';
import type { PostResource, UserResource } from '.';

export interface CommentResource
{
    id: number;
    content: string;
    is_flagged: boolean;
    flagged_at?: string | null;
    metadata: Record<string, unknown>;
    author?: UserResource;
    author_new?: UserResource;
    author_direct: UserResource;
    post?: PostResource;
    post_new?: PostResource;
    post_direct: PostResource;
    post_limited: { id: number; title: string };
    post_extended: { id: number; title: string; content: string; user_id: number; status: StatusType; published_at: string | null; metadata: unknown[] | null; rating: number | null; category: string; options: unknown[] | null; deleted_at: string | null; category_id: number | null; visibility: VisibilityType | null; priority: PriorityType | null; word_count: number | null; reading_time_minutes: number | null; featured_image_url: string | null; is_pinned: boolean; title_display: string | null; excerpt: string | null; reading_time: string; author: User; categoryRel: Category; comments: Comment[]; tags: Tag[]; images: Image[] } | null;
}
