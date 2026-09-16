import type { Comment, User } from '../../models';

/**
 * Relation filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * The method body's shape carries no import, so its filters publish types that name no model or enum.
 *
 * @see Workbench\App\Http\Resources\CommentRelationFiltersResource
 */
export interface CommentRelationFiltersResource
{
    id: number;
    summary: { id: number; author: { id: number; name: string }; author_role: Record<string, unknown> | null; post_fields: Record<string, unknown>; replies: unknown[]; kept_replies: unknown[] | null; reply_previews: { id: number; content: string }[] };
    picks: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] };
}
