import type { PriorityType, StatusType } from '../../enums';

/**
 * Reads the two-enum `review_priority` accessor through a value-less whenHas(), under a key no accessor shares.
 *
 * @see Workbench\App\Http\Resources\WarehouseReviewHasResource
 */
export interface WarehouseReviewHasResource
{
    id: number;
    review_level?: StatusType | PriorityType | null;
}
