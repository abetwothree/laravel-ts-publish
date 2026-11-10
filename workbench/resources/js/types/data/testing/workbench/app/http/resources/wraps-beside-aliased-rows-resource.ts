import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../crm/enums';
import { Role, Status as WorkbenchStatus } from '../../enums';
import type { StatusType as CrmStatusType } from '../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../enums';

/**
 * Wraps a status or a role beside rows only the `@return` types with two enums named `Status` bare (GH-154).
 *
 * @see Workbench\App\Http\Resources\WrapsBesideAliasedRowsResource
 */
export interface WrapsBesideAliasedRowsResource
{
    subject: AsEnum<typeof WorkbenchStatus> | AsEnum<typeof Role> | { crm: CrmStatusType; status: WorkbenchStatusType }[];
    crm_subject: AsEnum<typeof CrmStatus> | AsEnum<typeof Role> | { status: WorkbenchStatusType; crm: CrmStatusType }[];
}
