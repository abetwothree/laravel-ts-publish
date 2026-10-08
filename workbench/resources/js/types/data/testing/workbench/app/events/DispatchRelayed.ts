/** @see Workbench\App\Events\DispatchRelayed */
export interface DispatchRelayed {
    dispatchId: number;
    channel: string;
}
