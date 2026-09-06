import type { OrderItem } from '../../models';

/**
 * A guard-clause branch alongside a top-level `[key: number]` collection spread — regression
 * fixture for mergeReturnBranches() over-marking an index signature optional.
 *
 * @see Workbench\App\Http\Resources\GuardedCollectionSpreadResource
 */
export interface GuardedCollectionSpreadResource
{
    id: number;
    archived?: boolean;
    [key: number]: OrderItem;
}
