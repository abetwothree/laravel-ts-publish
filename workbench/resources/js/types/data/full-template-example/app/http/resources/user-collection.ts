import type { CursorPaginator, LengthAwarePaginator, SimplePaginator } from '@tolki/types';
import type { UserResource } from '.';

/** @see Workbench\App\Http\Resources\UserCollection */
export interface UserCollection
{
    data: UserResource[];
    has_admin: boolean;
    page: UserResource[] | LengthAwarePaginator<UserResource> | SimplePaginator<UserResource> | CursorPaginator<UserResource>;
    first_user: UserResource | null;
}
