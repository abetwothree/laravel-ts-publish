import { type AsEnum } from '@tolki/ts';

import { Status as WorkbenchStatus } from '../../enums';
import type { StatusType as CrmStatusType } from '../../../crm/enums';
import type { PriorityType, StatusType as WorkbenchStatusType } from '../../enums';

/**
 * A bare enum case in every position a resource key reads one, with two enums that share a basename. A backed case
 * sends its value; a pure case would make the response throw, so none is here.
 *
 * @see Workbench\App\Http\Resources\PostEnumCaseResource
 */
export interface PostEnumCaseResource
{
    status: WorkbenchStatusType;
    crm_status: CrmStatusType;
    kept_status: WorkbenchStatusType;
    priority_or_low: PriorityType;
    flagged: WorkbenchStatusType | null;
    either: WorkbenchStatusType | CrmStatusType;
    when_published?: WorkbenchStatusType;
    when_default: string | WorkbenchStatusType;
    matched: WorkbenchStatusType;
    pair: { app: WorkbenchStatusType; crm: CrmStatusType };
    cases: WorkbenchStatusType[];
    case_list: WorkbenchStatusType[];
    wrapped: AsEnum<typeof WorkbenchStatus>;
    wrapped_kept: AsEnum<typeof WorkbenchStatus>;
}
