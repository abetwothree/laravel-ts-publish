/** @see Workbench\App\Events\UserNotification */
export interface UserNotification {
    userId: number;
    title: string;
    message: string;
}
