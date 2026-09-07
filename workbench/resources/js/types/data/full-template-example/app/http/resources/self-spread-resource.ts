/**
 * Regression (Task 32 review, C1): a resource spreading itself must not recurse until memory is
 * exhausted. AstEngine::analyzeMethod()'s cycle guard returns an empty analysis for the re-entrant
 * call, so only 'marker' should ever appear.
 *
 * @see Workbench\App\Http\Resources\SelfSpreadResource
 */
export interface SelfSpreadResource
{
    marker: boolean;
}
