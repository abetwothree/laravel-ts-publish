import type { PostSnapshot } from '@js/types/snapshots';
import type { StatusType } from '../enums';

/** @see Workbench\App\Events\MixedTypesEvent */
export interface MixedTypesEvent {
    post: PostSnapshot;
    status: StatusType;
    message: string;
}
