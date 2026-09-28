import type { PriorityType, StatusType } from '../../enums';

/**
 * Reads the two-enum `review_priority` accessor through a value-less whenAppended(), under a key no accessor shares.
 *
 * @see Workbench\App\Http\Resources\WarehouseReviewAppendedResource
 */
export interface WarehouseReviewAppendedResource
{
    id: number;
    review_level?: StatusType | PriorityType | null;
}
