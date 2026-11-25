/**
 * A closure `@param` naming a class that is not a model, each beside its native twin (GH-134; plan task T2).
 *
 * @see Workbench\App\Http\Resources\DocblockReads\ParamTagClassesResource
 */
export interface ParamTagClassesResource
{
    private_amounts_by_param_tag: string[] | Record<string, string>;
    private_amounts_by_native_type: string[] | Record<string, string>;
    interface_labels_by_param_tag: string[] | Record<string, string>;
    interface_labels_by_native_type: string[] | Record<string, string>;
    backed_enum_values_by_param_tag: (string | number)[] | Record<string, string | number>;
    backed_enum_values_by_native_type: (string | number)[] | Record<string, string | number>;
    unit_enum_names_by_param_tag: string[] | Record<string, string>;
    unit_enum_names_by_native_type: string[] | Record<string, string>;
    collection_counts_by_param_tag: number[] | Record<string, number>;
    collection_counts_by_native_type: number[] | Record<string, number>;
    abstract_areas_by_param_tag: number[] | Record<string, number>;
    abstract_areas_by_native_type: number[] | Record<string, number>;
    loose_labels_by_param_tag: string[] | Record<string, string>;
    loose_labels_by_native_type: string[] | Record<string, string>;
}
