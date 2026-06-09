import type { Post, User } from '../models';

/** @see Workbench\App\Events\MultiModelEvent */
export interface MultiModelEvent {
    post: Partial<Post>;
    user: Partial<User>;
}
