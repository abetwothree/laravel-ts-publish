import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../../crm/enums';
import { Status as EnumsStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as EnumsStatusType } from '../../../enums';

/**
 * Rows keyed by name and mapped to the app's and the CRM's `Status` (GH-160, GH-76): wrapped, read, and in an object.
 * The list arm and the Record arm name each enum alike.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\KeyedSameNamedMapsResource
 */
export interface KeyedSameNamedMapsResource
{
    wraps: (AsEnum<typeof EnumsStatus> | AsEnum<typeof CrmStatus>)[] | Record<string, (AsEnum<typeof EnumsStatus> | AsEnum<typeof CrmStatus>)>;
    reads: (EnumsStatusType | CrmStatusType)[] | Record<string, (EnumsStatusType | CrmStatusType)>;
    objects: { app: EnumsStatusType; crm: CrmStatusType }[] | Record<string, { app: EnumsStatusType; crm: CrmStatusType }>;
}
