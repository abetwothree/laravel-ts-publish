/** @see Workbench\App\Events\UserSynced */
export interface UserSynced {
    userId: string;
    action: string;
}
