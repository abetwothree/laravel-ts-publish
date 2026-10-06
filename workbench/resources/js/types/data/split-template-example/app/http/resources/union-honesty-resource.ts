/**
 * Exercises: a union arm the engine cannot type is dropped, so the property publishes the arm that is left.
 *
 * One key per recording site, so the dropped-arm audit proves each site fires: the plain ternary and the Elvis go
 * through analyzeClosureUnion(), 'narrowed' through TernaryHandler's instanceof path, 'data_get_default' through
 * KnownFunctionCallHandler and 'conditional_default' through ConditionalMethodHandler. The audit pins these lines.
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
}
