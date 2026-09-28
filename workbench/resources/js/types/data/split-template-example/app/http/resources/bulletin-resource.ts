import type { Bulletin, Comment, User } from '../../models';

/**
 * Reads its own model's accessors through `$this->resource`, whenAppended() and whenHas(), under keys no accessor shares.
 *
 * @see Workbench\App\Http\Resources\BulletinResource
 */
export interface BulletinResource
{
    id: number;
    list: Comment[];
    picked?: Pick<User, 'id' | 'name'>;
    own?: Pick<Bulletin, 'id' | 'title'>;
}
