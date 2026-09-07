import { type AsEnum } from '@tolki/ts';

import { Status } from '../../enums';
import type { StatusType } from '../../enums';

/**
 * Regression fixture: a mixed EnumResource::collection()/direct-access ternary's per-arm shape
 * (Task 28) must survive ResourceAstAnalyzer::mergeReturnBranches(), not only the single-branch
 * analyzeReturnArray() path. Each `if`/`else` here returns its own array, so toArray() has more
 * than one top-level `return` and is merged rather than analyzed directly.
 *
 * @see Workbench\App\Http\Resources\MixedEnumReturnBranchesResource
 */
export interface MixedEnumReturnBranchesResource
{
    id: number;
    branch_active?: boolean;
    wrapped_history_or_scalar?: AsEnum<typeof Status>[] | StatusType;
}
