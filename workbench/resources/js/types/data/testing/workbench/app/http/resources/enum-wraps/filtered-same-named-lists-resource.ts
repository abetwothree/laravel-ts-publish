import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../../crm/enums';
import { Status as WorkbenchStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';
import type { Address as WorkbenchAddress2 } from '../../../models';
import type { Address as WorkbenchAddress } from '..';

/**
 * Lists array_filter() keeps of the app's and the CRM's `Status`, or of the `Address` model and its resource, each
 * beside a later token of that name (GH-76, GH-160). The resource re-indexes each filtered list, so every token after
 * one still names its own class.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\FilteredSameNamedListsResource
 */
export interface FilteredSameNamedListsResource
{
    either: (CrmStatusType | WorkbenchStatusType)[] | WorkbenchStatusType[];
    app_after: { list: (CrmStatusType | WorkbenchStatusType)[]; one: WorkbenchStatusType };
    crm_after: { list: (WorkbenchStatusType | CrmStatusType)[]; one: CrmStatusType };
    model_after: { list: (WorkbenchAddress | WorkbenchAddress2)[]; one: WorkbenchAddress2 | null };
    wrap_after: { list: (AsEnum<typeof CrmStatus> | AsEnum<typeof WorkbenchStatus>)[]; one: AsEnum<typeof WorkbenchStatus> };
    reads: (CrmStatusType | WorkbenchStatusType)[] | WorkbenchStatusType[];
    wraps: (AsEnum<typeof CrmStatus> | AsEnum<typeof WorkbenchStatus>)[] | AsEnum<typeof WorkbenchStatus>[];
    addresses: (WorkbenchAddress | WorkbenchAddress2)[] | (WorkbenchAddress2 | null)[];
    mixed: (WorkbenchAddress | AsEnum<typeof CrmStatus> | WorkbenchAddress2 | AsEnum<typeof WorkbenchStatus>)[] | AsEnum<typeof WorkbenchStatus>[];
}
