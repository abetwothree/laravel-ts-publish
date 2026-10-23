/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * Keys per recording site, each pinned by the audit: the ternary and the Elvis through analyzeClosureUnion(), then
 * 'narrowed', 'data_get_default', 'conditional_default', 'match_arm' and 'coalesce_right' through their handlers, the
 * 'binding_join' keys, 'walk_branch_join' and 'merge_branch_join' through a parameter's, a local's and a merge's join.
 *
 * @see Workbench\App\Http\Resources\UnionHonestyResource
 */
export interface UnionHonestyResource
{
    elvis: null;
    ternary: null;
    still_typed: string | null;
    narrowed: null;
    data_get_default: string | null;
    conditional_default: string;
    match_arm: string;
    coalesce_right: string;
    binding_join_old?: string;
    binding_join_written?: string;
    walk_branch_join?: { id: number; value: string };
    merge_branch_join: string;
}
