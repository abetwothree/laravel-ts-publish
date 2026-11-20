import { type AsEnum } from '@tolki/ts';

import { MediaType, Status } from '../../../enums';
import type { RoleType, StatusType } from '../../../enums';

/**
 * An enum wrap beside a bare read, a case, a `->value` or `->name` read, a literal or an object holding a read of the
 * enum (GH-160, GH-108). The wrap sends its object, every other arm what it reads.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\WrapBesideReadResource
 */
export interface WrapBesideReadResource
{
    wrap_or_helper_offset: AsEnum<typeof MediaType>;
    wrap_or_nested_value: AsEnum<typeof MediaType> | string;
    wrap_or_nested_nullable_value: AsEnum<typeof MediaType> | null;
    match_wrap_read_literal: AsEnum<typeof Status> | StatusType;
    match_wrap_value_name: AsEnum<typeof Status> | StatusType;
    match_wrap_value_other: AsEnum<typeof Status> | StatusType;
    nested_make_or_collection_or_read: AsEnum<typeof Status>[] | StatusType;
    collection_or_plain_list: AsEnum<typeof Status>[];
    wrap_or_other_enum_case: RoleType | StatusType;
    wrap_or_nested_nullable_case: AsEnum<typeof Status> | null;
    wrap_or_object_value: AsEnum<typeof MediaType> | { v: AsEnum<typeof MediaType> };
}
