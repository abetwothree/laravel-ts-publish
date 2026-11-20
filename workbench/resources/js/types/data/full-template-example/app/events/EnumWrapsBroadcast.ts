import type { StatusType, VisibilityType } from '../enums';

/** @see Workbench\App\Events\EnumWrapsBroadcast */
export interface EnumWrapsBroadcast {
    status: StatusType;
    visibility: VisibilityType | never[];
}
