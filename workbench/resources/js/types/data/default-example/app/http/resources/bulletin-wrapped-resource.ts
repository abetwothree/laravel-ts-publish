import type { Comment, User } from '../../models';

/**
 * Reads its model's accessors through a `$resource` property its docblock types, under keys no accessor shares.
 *
 * @see Workbench\App\Http\Resources\BulletinWrappedResource
 */
export interface BulletinWrappedResource
{
    list: Comment[];
    owned_by: User;
}
