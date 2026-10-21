/**
 * Regression fixture (Task 12 review, Critical 2): two TOP-LEVEL assignments to the
 * same variable, separated by a guard-clause return. Each branch reads the assignment
 * that reaches it: `early` the notes and `late` the key, never the one recorded last.
 *
 * @see Workbench\App\Http\Resources\LocalVarGuardClauseResource
 */
export interface LocalVarGuardClauseResource
{
    early?: string | null;
    late?: number;
}
