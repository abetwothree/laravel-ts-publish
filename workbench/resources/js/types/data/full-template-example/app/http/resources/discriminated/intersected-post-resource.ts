import type { PostPlacementVariants } from '@/types/placement';
import type { FeedPlacementResource, PinnedPlacementResource } from '.';

/**
 * GH-157's escape hatch: the keys publish flat, and a union written by hand is intersected with them, so `is_pinned`
 * still narrows `placement`.
 *
 * @see Workbench\App\Http\Resources\Discriminated\IntersectedPostResource
 */
export type IntersectedPostResource = {
    id: number;
    is_pinned: boolean;
    placement: PinnedPlacementResource | FeedPlacementResource;
} & (PostPlacementVariants);
