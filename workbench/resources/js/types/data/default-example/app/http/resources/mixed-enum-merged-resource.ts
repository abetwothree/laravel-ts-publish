import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';
import type { StatusType } from '../../enums';

/**
 * Regression fixture: a mixed EnumResource::collection()/direct-access ternary's per-arm shape
 * (Task 28) must survive every result collector, not only analyzeReturnArray(). Each property
 * below reaches a different collector that used to drop the shape and silently reproduce the
 * pre-fix (missing `[]`) answer.
 *
 * @see Workbench\App\Http\Resources\MixedEnumMergedResource
 */
export interface MixedEnumMergedResource
{
    id: number;
    wrapped_history_or_scalar_merged: AsEnum<typeof Status>[] | StatusType;
    assigned_marker: boolean;
    wrapped_history_or_scalar_assigned: AsEnum<typeof Status>[] | StatusType;
}
