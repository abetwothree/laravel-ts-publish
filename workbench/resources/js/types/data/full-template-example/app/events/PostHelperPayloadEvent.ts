/** @see Workbench\App\Events\PostHelperPayloadEvent */
export interface PostHelperPayloadEvent {
    post_id: number;
    post_title: string;
    row: { post_id: number; post_title: string };
}
