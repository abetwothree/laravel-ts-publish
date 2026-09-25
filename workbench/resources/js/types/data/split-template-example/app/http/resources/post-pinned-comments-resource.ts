import type { Comment } from '../../models';

/**
 * A relation loaded under a name the model does not declare, so only the local's inline `@var` names the collection
 * it holds.
 *
 * @see Workbench\App\Http\Resources\PostPinnedCommentsResource
 */
export interface PostPinnedCommentsResource
{
    id: number;
    pinned_count: number;
    pinned: Comment[];
}
