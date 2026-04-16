/** Exercises closure control-flow paths in collectReturnExpressions: elseif, else, switch, try/catch/finally, foreach, and do-while. */
export interface ClosureControlFlowResource
{
    id: number;
    buyer_info?: { role: unknown; name: string };
    status_label?: { label: unknown };
    safe_total?: { amount: number } | { amount: unknown };
    tags?: { first_item: string } | { first_item: unknown };
    retry_result?: { attempted: unknown };
}
