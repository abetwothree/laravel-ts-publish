import { type AsEnum } from '@tolki/ts';

import { Role, Status, WeekDays } from '../../enums';
import type { RoleType, StatusType } from '../../enums';

/**
 * Exercises EnumResource::collection() across its backing shapes: an accessor returning
 * list<Enum>, an AsEnumCollection cast, a first-class callable value, and a local
 * variable ->map() (not $this->relation->map()) — those should all emit an array-wrapped
 * AsEnum utility type, not the unresolved EnumResource itself. member_role_snapshot is the
 * one exception: a bare (unwrapped) enum read, pinning a distinct import-GC concern.
 *
 * @see Workbench\App\Http\Resources\EnumCollectionResource
 */
export interface EnumCollectionResource
{
    id: number;
    status_history: AsEnum<typeof Status>[];
    week_days: AsEnum<typeof WeekDays>[] | null;
    wrapped_week_days: { week_days: AsEnum<typeof WeekDays>[] | null };
    week_days_when_has?: AsEnum<typeof WeekDays>[] | null;
    week_days_when_has_default: AsEnum<typeof WeekDays>[] | string | null;
    status_history_when_appended?: AsEnum<typeof Status>[];
    members_via_var?: AsEnum<typeof Role>[];
    member_role_snapshot?: ({ role: RoleType | null })[];
    wrapped_status_fallback: { status: AsEnum<typeof Status>[] | StatusType };
    latest_status_or_history: AsEnum<typeof Status> | StatusType[];
    wrapped_history_or_scalar: AsEnum<typeof Status>[] | StatusType;
    wrapped_history_or_array: AsEnum<typeof Status>[] | StatusType[];
}
