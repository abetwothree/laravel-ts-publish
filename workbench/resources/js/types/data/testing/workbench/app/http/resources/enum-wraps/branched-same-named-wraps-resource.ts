import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../../crm/enums';
import { Status as WorkbenchStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';

/**
 * Branches writing a wrap or a read of the app's or the CRM's `Status` into one key (GH-160, GH-76). Each branch sends
 * its own enum, so the key keeps an arm for each, under its own alias.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\BranchedSameNamedWrapsResource
 */
export interface BranchedSameNamedWrapsResource
{
    written: AsEnum<typeof WorkbenchStatus> | AsEnum<typeof CrmStatus>;
    read: WorkbenchStatusType | CrmStatusType;
    nested: { w: AsEnum<typeof WorkbenchStatus> } | { w: AsEnum<typeof CrmStatus> };
}
