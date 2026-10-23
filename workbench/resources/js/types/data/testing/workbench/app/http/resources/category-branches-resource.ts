import type { CategoryResource } from '.';

/**
 * Exercises: a returned variable whose keys exclusive branches write. Each branch of an if chain holds its own value
 * for a key, so a key every branch of a chain ending in else writes is required with each branch's type, and a key a
 * chain without else writes keeps the value it held before as one more arm.
 *
 * @see Workbench\App\Http\Resources\CategoryBranchesResource
 */
export interface CategoryBranchesResource
{
    id: number;
    label: number | string;
    mode: number | string;
    top: CategoryResource | null;
    tier: string | number | boolean;
    first_only?: boolean;
    described?: boolean;
}
