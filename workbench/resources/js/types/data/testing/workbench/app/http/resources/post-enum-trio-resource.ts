import type { PriorityType, StatusType } from '../../enums';

/**
 * Three enum members, two of them the same enum: the import queue must carry three entries, not two.
 *
 * @see Workbench\App\Http\Resources\PostEnumTrioResource
 */
export interface PostEnumTrioResource
{
    id: number;
    trio: { a: StatusType; b: StatusType; c: PriorityType | null };
}
