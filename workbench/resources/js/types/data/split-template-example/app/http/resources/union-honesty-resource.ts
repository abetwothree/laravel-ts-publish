/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * One key per recording site; the audit proves each fires and pins these lines: the plain ternary and the Elvis go
 * through analyzeClosureUnion(), 'narrowed' through TernaryHandler's instanceof path, 'data_get_default' through
 * KnownFunctionCallHandler, 'conditional_default' through ConditionalMethodHandler, 'match_arm' through MatchHandler.
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
}
