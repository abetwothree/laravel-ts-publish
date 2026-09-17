import type { Comment, User } from '../../models';

/**
 * Reads Bulletin's accessors through a typed closure parameter and a nullsafe relation chain, beside a model method
 * body that reads them without imports. Keys differ from BulletinBoard's own accessors, so no name lookup imports them.
 *
 * @see Workbench\App\Http\Resources\BulletinBoardResource
 */
export interface BulletinBoardResource
{
    id: number;
    lists: Comment[][];
    lead_pick: Pick<User, 'id' | 'name'> | null;
    summary: { comment_lists: unknown[][]; lead_author: { id: number; name: string } | null; id: number };
}
