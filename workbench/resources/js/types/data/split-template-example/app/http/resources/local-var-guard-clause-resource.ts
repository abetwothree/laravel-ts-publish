/**
 * Regression fixture (Task 12 review, Critical 2): two TOP-LEVEL assignments to the
 * same variable, separated by a guard-clause return, must not resolve either return
 * branch through a single static binding — which assignment was "last" depends on
 * which branch actually ran, and this analyzer does not do flow analysis. Both
 * `early` and `late` must degrade to unknown rather than resolving through
 * whichever assignment happens to be recorded.
 *
 * @see Workbench\App\Http\Resources\LocalVarGuardClauseResource
 */
export interface LocalVarGuardClauseResource
{
    early?: string | null;
    late?: number;
}
