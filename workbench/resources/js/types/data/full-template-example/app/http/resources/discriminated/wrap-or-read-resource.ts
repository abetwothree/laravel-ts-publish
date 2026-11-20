import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';
import type { StatusType } from '../../../enums';

/**
 * Keys whose arms send one enum wrapped in an enum resource for one value and read directly for another (GH-157):
 * each value keeps its own arm, at the top level, in a nested literal and with the arms written in another order.
 *
 * @see Workbench\App\Http\Resources\Discriminated\WrapOrReadResource
 */
export type WrapOrReadResource = {
    reordered: { priority: 0; status: AsEnum<typeof Status>; tally: number } | { priority: 1; status: StatusType; tally: number } | { priority: 2 | 3 | null; status: null; tally: string };
} & (
    | { priority: 0; status: AsEnum<typeof Status>; tally: number }
    | { priority: 1; status: StatusType; tally: number }
    | { priority: 2 | 3 | null; status: null; tally: string }
) & (
    | { is_pinned: true; stage: AsEnum<typeof Status> }
    | { is_pinned: false; stage: StatusType }
);
