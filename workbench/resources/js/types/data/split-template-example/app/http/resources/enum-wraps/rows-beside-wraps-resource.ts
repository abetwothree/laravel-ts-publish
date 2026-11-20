import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { RoleType, StatusType as EnumsStatusType, VisibilityType } from '../../../enums';

/**
 * Rows of the app and the CRM `Status`, or of names, in a ternary before two or three enum wraps, and a `null` first
 * arm (GH-160). The rows send each enum's value, a wrap its object.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\RowsBesideWrapsResource
 */
export interface RowsBesideWrapsResource
{
    rows_then_two_wraps: { c: CrmStatusType; s: EnumsStatusType }[] | EnumsStatusType | RoleType;
    rows_then_three_wraps: { c: AsEnum<typeof EnumsStatus>; s: CrmStatusType }[] | AsEnum<typeof EnumsStatus> | RoleType | VisibilityType;
    named_rows_then_two_wraps: { n: string }[] | EnumsStatusType | RoleType;
    null_then_two_wraps: EnumsStatusType | RoleType | null;
}
