import type { AsEnum } from '@tolki/ts';
import { Status, Visibility } from '../enums';

/** @see Workbench\App\Events\EnumWrapsBroadcast */
export interface EnumWrapsBroadcast {
    status: AsEnum<typeof Status>;
    visibility: AsEnum<typeof Visibility> | never[];
}
