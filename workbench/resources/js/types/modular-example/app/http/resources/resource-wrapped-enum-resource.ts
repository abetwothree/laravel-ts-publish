import { type AsEnum } from '@tolki/enum';

import { Priority, Status, Visibility } from '../../enums';
import type { PriorityType, StatusType, VisibilityType } from '../../enums';

/** Exercises issue #43: EnumResource wrapping an enum accessed via `$this->resource->property` returns `unknown` instead of the correct `AsEnum` utility type. Each entry below represents a distinct code pattern where the enum is reached through the underlying model accessor (`$this->resource->prop`) rather than the Laravel Resource magic shorthand (`$this->prop`).  All entries should resolve to the same TypeScript type as their `$this->prop` counterparts. */
export interface ResourceWrappedEnumResource
{
    id: number;
    status_make: AsEnum<typeof Status>;
    status_new: AsEnum<typeof Status>;
    visibility_make: AsEnum<typeof Visibility> | null;
    priority_new: AsEnum<typeof Priority> | null;
    status_when_make?: AsEnum<typeof Status>;
    status_when_arrow?: AsEnum<typeof Status>;
    visibility_when_full?: AsEnum<typeof Visibility> | null;
    priority_when_not_null_make?: AsEnum<typeof Priority> | null;
    status_when_not_null_arrow?: AsEnum<typeof Status>;
    visibility_when_not_null_full?: AsEnum<typeof Visibility> | null;
    status_ternary_null: AsEnum<typeof Status> | null;
    status_ternary_both: AsEnum<typeof Status>;
    status_or_visibility_ternary: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;
    enums_array: { status: AsEnum<typeof Status>; visibility: AsEnum<typeof Visibility> | null; priority: AsEnum<typeof Priority> | null };
    mixed_enums_array: { status_type: StatusType; visibility_type: VisibilityType | null; priority_type: PriorityType | null; status_resource_type: StatusType; visibility_resource_type: VisibilityType | null; priority_resource_type: PriorityType | null; status_enum: AsEnum<typeof Status>; visibility_enum: AsEnum<typeof Visibility> | null; priority_enum: AsEnum<typeof Priority> | null };
    merged_status?: AsEnum<typeof Status>;
    merged_visibility?: AsEnum<typeof Visibility> | null;
    deferred_status?: AsEnum<typeof Status>;
    deferred_priority?: AsEnum<typeof Priority> | null;
    category_status?: AsEnum<typeof Status>;
    category_visibility?: AsEnum<typeof Visibility> | null;
}
