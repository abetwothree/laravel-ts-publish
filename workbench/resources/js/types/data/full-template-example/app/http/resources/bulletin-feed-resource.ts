import type { Comment, User } from '../../models';

/**
 * Reads Bulletin's accessors through a relation chain and an untyped closure parameter.
 *
 * @see Workbench\App\Http\Resources\BulletinFeedResource
 */
export interface BulletinFeedResource
{
    id: number;
    lead_list: Comment[];
    owner_list: User[];
}
