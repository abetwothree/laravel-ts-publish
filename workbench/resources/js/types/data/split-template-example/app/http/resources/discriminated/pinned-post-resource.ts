import type { FeedPlacementResource, PinnedPlacementResource } from '.';

/**
 * GH-157's own report: one key's resource follows a boolean the resource also sends, so `is_pinned` should narrow
 * `placement`.
 *
 * @see Workbench\App\Http\Resources\Discriminated\PinnedPostResource
 */
export interface PinnedPostResource
{
    id: number;
    is_pinned: boolean;
    placement: PinnedPlacementResource | FeedPlacementResource;
}
