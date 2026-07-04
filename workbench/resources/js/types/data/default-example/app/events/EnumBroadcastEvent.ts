import type { ColorType, StatusType } from '../enums';

/** @see Workbench\App\Events\EnumBroadcastEvent */
export interface EnumBroadcastEvent {
    status: StatusType;
    color: ColorType;
}
