/**
 * Merge closures whose returns include a partly read variable; collect() of a value or [] (GH-121 B7, GH-153 B8).
 *
 * @see Workbench\App\Http\Resources\Spreads\ClosureCollectReadsResource
 */
export interface ClosureCollectReadsResource
{
    values_of_keyed_local: string[];
    collected_keyed_local: unknown;
    values_of_nullable_map: string[];
    collected_nullable_map: unknown;
    values_of_nullable_shape: string[];
    collected_nullable_shape: unknown;
    values_of_nullable_list: string[];
    collected_nullable_list: string[];
    collected_split_or_empty: unknown;
    merged_when_lone_variable?: number;
    merged_literal_beside_variable: number;
    merged_literal_beside_appended: number;
    merged_when_literal_beside_variable?: number;
}
