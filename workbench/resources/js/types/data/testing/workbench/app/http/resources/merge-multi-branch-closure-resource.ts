import type { CurrencyType } from '../../enums';

/**
 * Exercises resolveArrayOrClosureToProperties with a multi-return closure passed to merge(). The closure has multiple branches returning different array shapes, which should be merged with union semantics.
 *
 * @see Workbench\App\Http\Resources\MergeMultiBranchClosureResource
 */
export interface MergeMultiBranchClosureResource
{
    id: number;
    archived_at?: string | null;
    total?: number;
    currency?: CurrencyType;
}
