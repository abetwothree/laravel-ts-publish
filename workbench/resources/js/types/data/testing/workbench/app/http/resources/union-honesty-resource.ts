/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * One key per recording site, each pinned by the audit: the plain ternary and the Elvis through analyzeClosureUnion(),
 * 'narrowed' through TernaryHandler's instanceof path, 'data_get_default' through KnownFunctionCallHandler, and
 * 'conditional_default', 'match_arm', 'coalesce_right' through ConditionalMethodHandler, MatchHandler, CoalesceHandler.
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
}
