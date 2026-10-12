import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus } from '../../enums';
import type { StatusType as CrmStatusType } from '../../../crm/enums';
import type { PriorityType, StatusType as EnumsStatusType } from '../../enums';

/**
 * A bare enum case in every position a resource key reads one, with two enums that share a basename. A backed case
 * sends its value; a pure case would make the response throw, so none is here.
 *
 * @see Workbench\App\Http\Resources\PostEnumCaseResource
 */
export interface PostEnumCaseResource
{
    status: EnumsStatusType;
    crm_status: CrmStatusType;
    kept_status: EnumsStatusType;
    priority_or_low: PriorityType;
    flagged: EnumsStatusType | null;
    either: EnumsStatusType | CrmStatusType;
    when_published?: EnumsStatusType;
    when_default: string | EnumsStatusType;
    matched: EnumsStatusType;
    pair: { app: EnumsStatusType; crm: CrmStatusType };
    cases: EnumsStatusType[];
    case_list: EnumsStatusType[];
    wrapped: AsEnum<typeof EnumsStatus>;
    wrapped_kept: AsEnum<typeof EnumsStatus>;
}
