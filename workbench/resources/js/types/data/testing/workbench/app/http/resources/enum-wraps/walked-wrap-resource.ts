import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';
import type { StatusType } from '../../../enums';

/**
 * A returned variable whose branches write one key as a wrap of the status or as the bare visibility (GH-134).
 *
 * @see Workbench\App\Http\Resources\EnumWraps\WalkedWrapResource
 */
export interface WalkedWrapResource
{
    id: number;
    state: AsEnum<typeof Status> | StatusType | null;
}
