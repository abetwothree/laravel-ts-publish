import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';
import type { StatusType } from '../../../enums';

/**
 * A wrap, a bare read of the same enum and rows the `@return` fills (GH-160). The read sends the enum's value.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\DeclaredWrapRowsResource
 */
export interface DeclaredWrapRowsResource
{
    subject: AsEnum<typeof Status> | StatusType | { s: StatusType }[];
}
