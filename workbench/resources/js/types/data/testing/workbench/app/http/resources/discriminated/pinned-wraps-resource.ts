import { type AsEnum } from '@tolki/ts';

import { Status as WorkbenchStatus, Visibility } from '../../../enums';
import type { StatusType as CrmStatusType } from '../../../../crm/enums';
import type { StatusType as WorkbenchStatusType } from '../../../enums';
import type { UserResource } from '..';

/**
 * Keys a boolean the resource sends picks, whose arms need imports: an enum resource of two enums, two same-named
 * enums and a resource only one arm sends (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\PinnedWrapsResource
 */
export type PinnedWrapsResource =
    | { is_pinned: true; state: AsEnum<typeof WorkbenchStatus>; stage: WorkbenchStatusType; author: UserResource }
    | { is_pinned: false; state: AsEnum<typeof Visibility> | null; stage: CrmStatusType; author?: never };
