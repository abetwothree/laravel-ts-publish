import type { OrderStatusType } from '../../enums';
import type { UserResource } from '.';

/**
 * Keyless list literals publish the JSON arrays they encode as: a constant one by its values, any other by its
 * elements' types, a spread of a list adding its elements, and an element no rule types leaving the element `unknown`.
 *
 * @see Workbench\App\Http\Resources\HandoverListResource
 */
export interface HandoverListResource
{
    stages: string[];
    stamps: (number | string | null)[];
    lanes: number[];
    route: { stops: string[]; hops: number };
    empty: never[];
    stops: string[];
    statuses: OrderStatusType[];
    senders: (UserResource | null)[];
    legs: ({ from: string; to: string } | { from: string; to: number })[];
    grid: number[][];
    flags: string[];
    opaque: unknown[];
    collected: (number | string | null)[];
}
