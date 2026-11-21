import { type AsEnum } from '@tolki/ts';

import { Status as CrmStatus } from '../../../../crm/enums';
import { Status as EnumsStatus } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as EnumsStatusType } from '../../../enums';
import type { Address as ModelsAddress } from '../../../models';
import type { Address as ResourcesAddress } from '..';

/**
 * Lists array_filter() keeps of the app's and the CRM's `Status`, or of the `Address` model and its resource, each
 * beside a later token of that name (GH-76, GH-160). The resource re-indexes each filtered list, so every token after
 * one still names its own class.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\FilteredSameNamedListsResource
 */
export interface FilteredSameNamedListsResource
{
    either: (CrmStatusType | EnumsStatusType)[] | EnumsStatusType[];
    app_after: { list: (CrmStatusType | EnumsStatusType)[]; one: EnumsStatusType };
    crm_after: { list: (EnumsStatusType | CrmStatusType)[]; one: CrmStatusType };
    model_after: { list: (ResourcesAddress | ModelsAddress)[]; one: ModelsAddress | null };
    wrap_after: { list: (AsEnum<typeof CrmStatus> | AsEnum<typeof EnumsStatus>)[]; one: AsEnum<typeof EnumsStatus> };
    reads: (CrmStatusType | EnumsStatusType)[] | EnumsStatusType[];
    wraps: (AsEnum<typeof CrmStatus> | AsEnum<typeof EnumsStatus>)[] | AsEnum<typeof EnumsStatus>[];
    addresses: (ResourcesAddress | ModelsAddress)[] | (ModelsAddress | null)[];
    mixed: (ResourcesAddress | AsEnum<typeof CrmStatus> | ModelsAddress | AsEnum<typeof EnumsStatus>)[] | AsEnum<typeof EnumsStatus>[];
}
