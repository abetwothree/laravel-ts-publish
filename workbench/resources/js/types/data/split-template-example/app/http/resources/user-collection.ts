import type { UserResource } from '.';

/**
 * @see Workbench\App\Http\Resources\UserCollection
 */
export interface UserCollection
{
    data: UserResource[];
    has_admin: unknown;
}
