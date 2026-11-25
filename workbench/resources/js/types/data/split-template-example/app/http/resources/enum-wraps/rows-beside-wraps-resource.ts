import { type AsEnum } from '@tolki/ts';

import { Role, Status as EnumsStatus, Visibility } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as EnumsStatusType } from '../../../enums';

/**
 * Rows of the app and the CRM `Status`, or of names, in a ternary before two or three enum wraps, and a `null` first
 * arm (GH-160). The rows send each enum's value, a wrap its object.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\RowsBesideWrapsResource
 */
export interface RowsBesideWrapsResource
{
    rows_then_two_wraps: { c: CrmStatusType; s: EnumsStatusType }[] | AsEnum<typeof EnumsStatus> | AsEnum<typeof Role>;
    rows_then_three_wraps: { c: CrmStatusType; s: EnumsStatusType }[] | AsEnum<typeof EnumsStatus> | AsEnum<typeof Role> | AsEnum<typeof Visibility>;
    named_rows_then_two_wraps: { n: string }[] | AsEnum<typeof EnumsStatus> | AsEnum<typeof Role>;
    null_then_two_wraps: AsEnum<typeof EnumsStatus> | AsEnum<typeof Role> | null;
}
