/** @see Workbench\App\Events\DeferredNotification */
export interface DeferredNotification {
    occurredAt: string;
    note: string | null;
    userId: number;
    title: string;
}
