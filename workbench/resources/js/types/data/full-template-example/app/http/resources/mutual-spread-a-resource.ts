/**
 * Regression (Task 32 review, C1): two resources spreading each other must not recurse until
 * memory is exhausted. Mirrors MutualSpreadBResource — see its docblock for the shared rationale.
 *
 * @see Workbench\App\Http\Resources\MutualSpreadAResource
 */
export interface MutualSpreadAResource
{
    a_marker: boolean;
    b_marker: boolean;
}
