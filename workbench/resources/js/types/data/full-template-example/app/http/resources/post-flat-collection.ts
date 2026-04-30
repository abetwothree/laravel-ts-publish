import type { PostResource } from '.';

/**
 * A ResourceCollection with $wrap = null so the collection IS the array, not wrapped in a 'data' key. Uses #[Collects] to identify the singular resource.
 *
 * @see Workbench\App\Http\Resources\PostFlatCollection
 */
export type PostFlatCollection = PostResource[];
