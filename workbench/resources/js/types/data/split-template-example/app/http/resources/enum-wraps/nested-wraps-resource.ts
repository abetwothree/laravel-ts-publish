import { type AsEnum } from '@tolki/ts';

import { Role, Status, Visibility } from '../../../enums';
import type { MediaTypeType, PriorityType, RoleType, StatusType, VisibilityType } from '../../../enums';

/**
 * Two or more enum wraps below a top-level key, in a list, beside an object arm holding a wrap, and through `when()`
 * (GH-160, GH-108). Each wrap sends its enum resource's object.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\NestedWrapsResource
 */
export interface NestedWrapsResource
{
    under_key: { subject: StatusType | RoleType };
    in_list: (StatusType | RoleType)[];
    two_levels: { outer: { inner: StatusType | RoleType } };
    three_under_key: { subject: AsEnum<typeof Status> | RoleType | VisibilityType };
    three_top: AsEnum<typeof Role> | StatusType | VisibilityType;
    three_wraps_two_enums: AsEnum<typeof Status> | PriorityType | null;
    wrap_or_object_wrap: AsEnum<typeof Status> | { inner: AsEnum<typeof Visibility> };
    when_wrap_or_object_wrap: AsEnum<typeof Status> | { inner: AsEnum<typeof Visibility> };
    when_two_wraps_one_nullable: StatusType | MediaTypeType | null;
}
