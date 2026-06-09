import type { StatusType } from '../enums';
import type { Post } from '../models';

/** @see Workbench\App\Events\MixedTypesEvent */
export interface MixedTypesEvent {
    post: Partial<Post>;
    status: StatusType;
    message: string;
}
