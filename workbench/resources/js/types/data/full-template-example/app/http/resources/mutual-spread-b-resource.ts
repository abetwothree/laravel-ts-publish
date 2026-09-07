/**
 * Regression (Task 32 review, C1): the other half of the mutual pair. A spreads B, B spreads A —
 * AstEngine::analyzeMethod()'s cycle guard must break the loop wherever it's first re-entered.
 *
 * @see Workbench\App\Http\Resources\MutualSpreadBResource
 */
export interface MutualSpreadBResource
{
    a_marker: boolean;
    b_marker: boolean;
}
