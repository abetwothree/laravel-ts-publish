import { type AsEnum } from '@tolki/ts';

import { MediaType, Priority, Role, Status, Visibility } from '../../../enums';

/**
 * Two or more enum wraps below a top-level key, in a list, beside an object arm holding a wrap, and through `when()`
 * (GH-160, GH-108). Each wrap sends its enum resource's object.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\NestedWrapsResource
 */
export interface NestedWrapsResource
{
    under_key: { subject: AsEnum<typeof Status> | AsEnum<typeof Role> };
    in_list: (AsEnum<typeof Status> | AsEnum<typeof Role>)[];
    two_levels: { outer: { inner: AsEnum<typeof Status> | AsEnum<typeof Role> } };
    three_under_key: { subject: AsEnum<typeof Status> | AsEnum<typeof Role> | AsEnum<typeof Visibility> };
    three_top: AsEnum<typeof Role> | AsEnum<typeof Status> | AsEnum<typeof Visibility>;
    three_wraps_two_enums: AsEnum<typeof Status> | AsEnum<typeof Priority> | null;
    wrap_or_object_wrap: AsEnum<typeof Status> | { inner: AsEnum<typeof Visibility> };
    when_wrap_or_object_wrap: AsEnum<typeof Status> | { inner: AsEnum<typeof Visibility> };
    when_two_wraps_one_nullable: AsEnum<typeof Status> | AsEnum<typeof MediaType> | null;
}
