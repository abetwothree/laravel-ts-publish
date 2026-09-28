import type { Bulletin, Comment, User } from '../../models';

/**
 * Reads Bulletin's accessors inside whenLoaded() closures: a relation chain, a closure parameter and pluck().
 *
 * @see Workbench\App\Http\Resources\BulletinLoadedResource
 */
export interface BulletinLoadedResource
{
    id: number;
    lead_list?: Comment[];
    lead_owner?: User;
    own_picks?: (Pick<Bulletin, 'id' | 'title'>)[];
}
