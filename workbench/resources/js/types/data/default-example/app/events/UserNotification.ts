import type { HasTimestamps } from '@/types/common';

/** @see Workbench\App\Events\UserNotification */
export interface UserNotification extends HasTimestamps {
    userId: number;
    title: string;
    message: string;
}
