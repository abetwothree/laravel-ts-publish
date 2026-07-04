import type { RoleType, VisibilityType } from '../enums';

/** @see Workbench\App\Events\PureEnumEvent */
export interface PureEnumEvent {
    role: RoleType;
    visibility: VisibilityType;
    action: string;
}
