import type { FeedPlacementResource, PinnedPlacementResource } from '.';

/**
 * GH-157's own report: one key's resource follows a boolean the resource also sends, so `is_pinned` should narrow
 * `placement`.
 *
 * @see Workbench\App\Http\Resources\Discriminated\PinnedPostResource
 */
export type PinnedPostResource = {
    id: number;
} & (
    | { is_pinned: true; placement: PinnedPlacementResource }
    | { is_pinned: false; placement: FeedPlacementResource }
);
