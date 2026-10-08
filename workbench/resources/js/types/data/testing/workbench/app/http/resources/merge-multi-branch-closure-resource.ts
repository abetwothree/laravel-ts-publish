import type { CurrencyType } from '../../enums';

/**
 * Exercises resolveMergedBranches() with a multi-return closure
 * passed to merge(). The closure has multiple branches returning different
 * array shapes, which should be merged with union semantics.
 *
 * @see Workbench\App\Http\Resources\MergeMultiBranchClosureResource
 */
export interface MergeMultiBranchClosureResource
{
    id: number;
    archived_at?: string | null;
    total?: string;
    currency?: CurrencyType;
}
