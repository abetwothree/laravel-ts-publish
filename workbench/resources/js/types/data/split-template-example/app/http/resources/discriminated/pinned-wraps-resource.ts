import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus, Visibility } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as EnumsStatusType } from '../../../enums';
import type { UserResource } from '..';

/**
 * Keys a boolean the resource sends picks, whose arms need imports: an enum resource of two enums, two same-named
 * enums and a resource only one arm sends (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\PinnedWrapsResource
 */
export interface PinnedWrapsResource
{
    is_pinned: boolean;
    state: AsEnum<typeof EnumsStatus> | AsEnum<typeof Visibility> | null;
    stage: EnumsStatusType | CrmStatusType;
    author?: UserResource;
}
