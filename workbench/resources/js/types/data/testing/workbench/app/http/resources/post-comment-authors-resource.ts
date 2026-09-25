/**
 * A map closure over a relation held in an untyped local, whose parameter names its model. A one-step read and a
 * nullsafe chain from that parameter must both resolve against it, with and without an Elvis default. An untyped
 * parameter over a to-many whenLoaded receiver takes the receiver's element model the same way.
 *
 * @see Workbench\App\Http\Resources\PostCommentAuthorsResource
 */
export interface PostCommentAuthorsResource
{
    id: number;
    authors: ({ id: number; who: string | null; who_or: string | null })[];
    loaded?: ({ who: string | null })[];
}
