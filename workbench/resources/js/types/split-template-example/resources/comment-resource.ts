import type { PostResource, UserResource } from './';

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
}
