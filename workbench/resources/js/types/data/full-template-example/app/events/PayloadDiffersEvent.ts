/** @see Workbench\App\Events\PayloadDiffersEvent */
export interface PayloadDiffersEvent {
    team: number;
    kind: string;
    count: number;
}
