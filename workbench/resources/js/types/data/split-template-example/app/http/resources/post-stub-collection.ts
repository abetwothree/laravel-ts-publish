import type { PostResource } from '.';

/**
 * Keeps the `make:resource --collection` stub over `$collects`. Laravel sends the list it collects
 * as it sends a body-less collection's: `{ data: PostResource[] }`.
 *
 * @see Workbench\App\Http\Resources\PostStubCollection
 */
export interface PostStubCollection
{
    data: PostResource[];
}
