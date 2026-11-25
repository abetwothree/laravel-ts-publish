import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../../crm/enums';
import { Status as WorkbenchStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';

/**
 * Rows keyed by name and mapped to the app's and the CRM's `Status` (GH-160, GH-76): wrapped, read, and in an object.
 * The list arm and the Record arm name each enum alike.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\KeyedSameNamedMapsResource
 */
export interface KeyedSameNamedMapsResource
{
    wraps: (AsEnum<typeof WorkbenchStatus> | AsEnum<typeof CrmStatus>)[] | Record<string, (AsEnum<typeof WorkbenchStatus> | AsEnum<typeof CrmStatus>)>;
    reads: (WorkbenchStatusType | CrmStatusType)[] | Record<string, (WorkbenchStatusType | CrmStatusType)>;
    objects: { app: WorkbenchStatusType; crm: CrmStatusType }[] | Record<string, { app: WorkbenchStatusType; crm: CrmStatusType }>;
}
