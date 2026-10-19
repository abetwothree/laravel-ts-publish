import type { UserResource } from '.';

/**
 * Resources built around a payload that is only null, which JsonResource::filter() sends as null.
 *
 * @see Workbench\App\Http\Resources\PostNullWrapResource
 */
export interface PostNullWrapResource
{
    null_new: null;
    null_make: null;
    null_default?: null;
    null_local: null;
    author_new: UserResource;
    nested: { author: null };
}
