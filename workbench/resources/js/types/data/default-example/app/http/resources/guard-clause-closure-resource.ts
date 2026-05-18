/**
 * Exercises the bug where resolveClosureReturnExpression() picks the first Return_ statement in a closure — which is the guard-clause `return null` instead of the actual data array. The closure has: if (! $this->user) { return null; }  ← guard clause (first return) return [ 'name' => ..., 'email' => ... ];  ← actual data (should be picked)
 *
 * @see Workbench\App\Http\Resources\GuardClauseClosureResource
 */
export interface GuardClauseClosureResource
{
    id: number;
    total: number;
    buyer?: { name: string; email: string } | null;
}
