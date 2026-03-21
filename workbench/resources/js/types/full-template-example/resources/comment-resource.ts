import type { PostResource, UserResource } from './';

export interface CommentResource
{
    id: number;
    content: string;
    is_flagged: boolean;
    flagged_at?: string | null;
    metadata: Record<string, unknown>;
    author?: UserResource;
    post?: PostResource;
}
