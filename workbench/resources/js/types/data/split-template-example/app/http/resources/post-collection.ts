import type { PostResource } from '.';

/**
 * @see Workbench\App\Http\Resources\PostCollection
 */
export interface PostCollection
{
    data: PostResource[];
}
