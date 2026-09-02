import type { PostResource } from '.';

/**
 * Inherits `$wrap = null` and declares nothing else — the delegated analysis must still see it.
 *
 * @see Workbench\App\Http\Resources\PostUnwrappedCollection
 */
export type PostUnwrappedCollection = PostResource[];
