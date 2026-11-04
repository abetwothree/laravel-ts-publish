import type { Comment, User } from '../../models';

/**
 * Relation filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * The method body's filters publish what the accessor's do, naming the models they hold.
 *
 * @see Workbench\App\Http\Resources\CommentRelationFiltersResource
 */
export interface CommentRelationFiltersResource
{
    id: number;
    summary: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] };
    summary_via_resource: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] };
    picks: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] };
    picks_summary: { id: number; picks: { id: number; author: Pick<User, 'id' | 'name'>; author_role: Pick<User, 'id' | 'role'> | null; post_fields: Record<string, unknown>; replies: Comment[]; kept_replies: Comment[] | null; reply_previews: { id: number; content: string }[] } };
}
