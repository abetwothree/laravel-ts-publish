import { type AsEnum } from '@tolki/ts';

import { Status, Visibility } from '../../../enums';

/**
 * Enum wraps a helper returns or is passed (GH-108): a ternary of two wraps, a wrap over `whenHas()`, and a seed the
 * helper writes a key into and returns.
 *
 * @see Workbench\App\Http\Resources\EnumWraps\WrapHelpersResource
 */
export interface WrapHelpersResource
{
    picked: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;
    status_wrap?: AsEnum<typeof Status>;
    card: { stamped: boolean; [key: string]: unknown };
}
