import { type AsEnum } from '@tolki/ts';

import { Status } from '../enums';
import type { StatusType } from '../enums';
import type { User } from '.';

/**
 * A docblock union naming a class or an enum both inside a container and bare publishes both arms, in either order:
 * an `@property` tag, a cast's get() `@return` and an accessor's `Attribute<…>`. The enum column and accessor keep
 * the model's Resource interface, printed as a list of the AsEnum wrap or the wrap alone.
 *
 * @see Workbench\App\Models\DocblockUnionFixture
 */
export interface DocblockUnionFixture
{
    // Columns
    id: number;
    tags: User[] | User | null;
    related_users: User[] | User | null;
    meta_info: StatusType[] | StatusType | null;
    owner_snapshot: Record<string, User> | User | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** The grades, a list of statuses or one status. */
    grades: StatusType[] | StatusType;
}

export interface DocblockUnionFixtureResource extends Omit<DocblockUnionFixture, 'meta_info' | 'grades'>
{
    meta_info: AsEnum<typeof Status>[] | AsEnum<typeof Status> | null;
    grades: AsEnum<typeof Status>[] | AsEnum<typeof Status>;
}
