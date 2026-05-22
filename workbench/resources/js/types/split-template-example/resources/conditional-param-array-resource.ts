/** Exercises issue #38: closure parameter passed by the conditional method. Each field uses a single-param arrow function that returns an inline array literal. The bug: the analyzer resolves the return type as `unknown` instead of inferring the array shape `{ id: number; email: string; name: string }`. */
export interface ConditionalParamArrayResource
{
    id: number;
    user_summary?: { id: number; email: string; name: string };
    notes_or_default?: unknown;
    user_meta?: { profile: { name: string; email: string }; verified: boolean };
}
