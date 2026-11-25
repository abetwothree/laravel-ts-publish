import { type AsEnum } from '@tolki/ts';

import { MediaType, Role, Status } from '../../../enums';
import type { MediaTypeType, StatusKind, StatusType } from '../../../enums';

/**
 * An enum wrap beside a bare read, a case, a `->value` or `->name` read, a literal or an object holding a read of the
 * enum (GH-160, GH-108). The wrap sends its object, every other arm what it reads.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\WrapBesideReadResource
 */
export interface WrapBesideReadResource
{
    wrap_or_helper_offset: AsEnum<typeof MediaType> | MediaTypeType;
    wrap_or_nested_value: AsEnum<typeof MediaType> | MediaTypeType | string;
    wrap_or_nested_nullable_value: AsEnum<typeof MediaType> | MediaTypeType | null;
    match_wrap_read_literal: AsEnum<typeof Status> | StatusType | string;
    match_wrap_value_name: AsEnum<typeof Status> | StatusType | StatusKind;
    match_wrap_value_other: AsEnum<typeof Status> | StatusType | string;
    nested_make_or_collection_or_read: AsEnum<typeof Status> | AsEnum<typeof Status>[] | StatusType;
    collection_or_plain_list: AsEnum<typeof Status>[] | StatusType[];
    wrap_or_other_enum_case: AsEnum<typeof Role> | StatusType;
    wrap_or_nested_nullable_case: AsEnum<typeof Status> | StatusType | null;
    wrap_or_object_value: AsEnum<typeof MediaType> | { v: MediaTypeType };
}
