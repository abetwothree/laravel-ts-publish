/** @see Workbench\App\Events\TeamMessageSent */
export interface TeamMessageSent {
    teamId: number;
    content: string;
}
