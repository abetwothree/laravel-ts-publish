import type { PriorityType } from '../../../enums';

/**
 * The placement a pinned post sends: one arm of PinnedPostResource's discriminated union (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\PinnedPlacementResource
 */
export interface PinnedPlacementResource
{
    rank: PriorityType | null;
    pinned_at: string | null;
}
