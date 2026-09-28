import type { Bulletin, Comment, User } from '../../models';

/**
 * Reads Bulletin's accessors through pluck() and inside a shape a closure parameter builds.
 *
 * @see Workbench\App\Http\Resources\BulletinArchiveResource
 */
export interface BulletinArchiveResource
{
    id: number;
    plucked: Comment[][];
    rows: ({ author: Pick<User, 'id' | 'name'> })[];
    own_picks: (Pick<Bulletin, 'id' | 'title'>)[];
}
