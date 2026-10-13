import type { StatusType } from '../../enums';
import type { Comment, User } from '../../models';

/**
 * A docblock union naming a class or an enum both inside a container and bare publishes both arms: a model's
 * `@property`, a property's `@var`, a helper's `@return` and a local's `@var`.
 *
 * @see Workbench\App\Http\Resources\BareBesideContainerResource
 */
export interface BareBesideContainerResource
{
    tags: User[] | User | null;
    related_users: User[] | User | null;
    crew: User[] | User | null;
    lead: User[] | User;
    statuses: StatusType[] | StatusType;
    either: Comment[] | Comment;
}
