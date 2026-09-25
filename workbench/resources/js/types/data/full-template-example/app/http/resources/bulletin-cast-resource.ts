import type { User } from '../../models';

/**
 * Overrides two reads of Bulletin's accessors, so neither `Comment` nor the `User` of `lead_pick` survives in their
 * types. `owner_list` still names `User`, so only `Comment` has nothing left to import.
 *
 * @see Workbench\App\Http\Resources\BulletinCastResource
 */
export interface BulletinCastResource
{
    id: number;
    lists: { id: number; content: string }[][];
    lead_pick: { id: number; name: string } | null;
    owner_list: User[];
}
