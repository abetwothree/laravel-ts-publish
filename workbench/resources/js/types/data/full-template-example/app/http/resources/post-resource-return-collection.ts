import type { PostResource } from '.';

/**
 * Returns `$this->resource`, the items it collects. Laravel sends them as it sends a body-less
 * collection's: `{ data: PostResource[] }`.
 *
 * @see Workbench\App\Http\Resources\PostResourceReturnCollection
 */
export interface PostResourceReturnCollection
{
    data: PostResource[];
}
