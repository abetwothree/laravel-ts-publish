import { type AsEnum } from '@tolki/ts';

import { Role, Status } from '../../enums';
import type { RoleType, StatusType } from '../../enums';

/**
 * Wraps a post's status or its author's role, else sends rows only the `@return` types with both enums bare (GH-154).
 *
 * @see Workbench\App\Http\Resources\WrapsBesideFilledRowsResource
 */
export interface WrapsBesideFilledRowsResource
{
    subject: AsEnum<typeof Status> | AsEnum<typeof Role> | { status: StatusType; role: RoleType }[];
}
