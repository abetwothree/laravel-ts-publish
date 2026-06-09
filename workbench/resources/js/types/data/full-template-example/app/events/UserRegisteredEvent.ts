import type { User } from '../models';

/** @see Workbench\App\Events\UserRegisteredEvent */
export interface UserRegisteredEvent {
    user: Partial<User>;
    userId: number;
}
