/**
 * Walk bases over an earlier key, `??=` of a key the walk lacks, resource spreads in merges (GH-161 B2a, B2b, B3, B6).
 *
 * @see Workbench\App\Http\Resources\Spreads\BaseFormsSpreadResource
 */
export interface BaseFormsSpreadResource
{
    ternary_local_coalesced: { id: number; by: { name: string | null; email: string | null } };
    ternary_local_given_key: { id: number; by: { name: string | null; email: string | null } };
    match_local_given_key: { id: number; by: { name: string | null; email: string | null } };
    elvis_local_given_key: { id: number; by: { name: string | null; email: string | null } };
    elvis_base_helper: { id: number; by: { name: string | null; email: string | null } };
    plus_assigned_ternary_helper: { id: number; by: { name: string | null; email: string | null }; n: number };
    ternary_base_in_if_helper: { id: number; by: { name: string | null; email: string | null }; n?: number };
    merged_ternary_helper: { id: number; by: { name: string | null; email: string | null } };
    copied_ternary_helper: { id: number; by: { name: string | null; email: string | null } };
    nested_coalesced_helper: { row: { id: number; by: { name: string | null; email: string | null } } };
    static_coalesced_helper: { id: number; by: { name: string | null; email: string | null } };
    flag_then_coalesced_helper: { id: number; by: boolean };
    coalesced_under_exact_return: { id: number; by: { name: string | null; email: string | null } };
    documented_coalesced_helper: { id: number; by: { name: string | null; email: string | null } };
    ternary_only_helper: { id: number; by: { name: string | null; email: string | null } };
    match_base_helper: { id: number; by: { name: string | null; email: string | null } };
    returned_ternary_helper: { id: number; by: { name: string | null; email: string | null } };
    old_coalesce_idiom_helper: { id: number; by: string };
    plus_default_over_ternary: { by: string };
    joined_local_base: { c: number };
    returned_whole_ternary: Record<string, unknown>;
    ternary_base_unheld_coalesce: { id: number; by: { name: string | null; email: string | null } };
    literal_base_unheld_coalesce: { id: number; by: { name: string | null; email: string | null }; n: number };
    helper_base_unheld_coalesce: { id: number; by: { name: string | null; email: string | null }; n: number };
    unheld_coalesce: { a: number };
    credited_title: number;
    name: number;
}
