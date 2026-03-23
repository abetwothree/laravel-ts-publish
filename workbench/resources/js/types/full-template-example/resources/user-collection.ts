import type { UserResource } from './';

export interface UserCollection
{
    data: UserResource[];
    has_admin: unknown;
}
