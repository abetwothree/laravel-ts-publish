import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as EnumsStatusType } from '../../../enums';

/**
 * Wraps and reads of the app and the CRM `Status`, two enums that share a name (GH-160, GH-76). Each arm sends its own
 * enum, so each keeps its own alias.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\SameNamedWrapsResource
 */
export interface SameNamedWrapsResource
{
    app_or_crm_wrap: EnumsStatusType;
    app_or_crm_wrap_when: EnumsStatusType;
    app_or_crm_wrap_under_key: { subject: EnumsStatusType };
    wrap_or_two_reads: AsEnum<typeof EnumsStatus>;
    crm_spread_over_wrap: { c: AsEnum<typeof EnumsStatus> };
    literals_nested_crm: { s: EnumsStatusType; x: { c: CrmStatusType } } | { s: CrmStatusType; x: null };
}
