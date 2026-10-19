/** @see Workbench\App\Events\TaggedPayloadEvent */
export interface TaggedPayloadEvent {
    id: number;
    [key: `${string}_tag`]: string | undefined;
}
