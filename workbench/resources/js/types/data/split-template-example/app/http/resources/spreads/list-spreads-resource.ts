/**
 * Lists and keyless values beside maps and named keys, and maps alone that may be empty (GH-161 D1, D2, D4a, D4b, D5).
 *
 * @see Workbench\App\Http\Resources\Spreads\ListSpreadsResource
 */
export interface ListSpreadsResource
{
    labels_method_then_ints: Record<string, string>;
    ints_then_labels: Record<string, string>;
    labels_then_ints_under_return: Record<string, string>;
    labels_then_array_values: Record<string, string>;
    labels_then_helper_list: Record<string, string>;
    labels_then_local_list: Record<string, string>;
    labels_then_other_list_method: Record<string, string>;
    labels_then_keyless_count: Record<string, string>;
    labels_then_nullable_list: Record<string, string>;
    labels_then_rows: Record<string, string>;
    labels_then_static_helper_list: Record<string, string>;
    ints_plus_key: { x: number };
    local_built_labels_then_ints: Record<string, string>;
    local_ints_then_key_over_key: { by: string; z: number };
    keyless_item_beside_key: { a: number };
    list_plus_keyed: { a: string };
    keyed_plus_list: { a: string };
    coalesced_map_alone: Record<string, unknown>;
    local_map_list_over_key: { by: { name: string | null; email: string | null } | string };
    map_bool_key_list: Omit<Record<string, string>, 'x'> & { x: boolean };
    map_list_bool_key: Omit<Record<string, string>, 'x'> & { x: boolean };
    map_list_nullable_key: Omit<Record<string, string>, 'note'> & { note: string | null };
    map_nullable_key: Omit<Record<string, string>, 'note'> & { note: string | null };
    labels_method_plus_key: { by: number | string };
    empty_default_map_alone: Record<string, string>;
    preset_default_map_alone: Record<string, string>;
    literal_body_map_alone: Record<string, string>;
    literal_body_map_then_counts: Record<string, string | number>;
    literal_body_map_then_labels: Record<string, string>;
    emptiness_guarded_spread_throws: { row: Record<string, unknown> };
    emptiness_guarded_spread_returns: { n?: number; row?: Record<string, unknown> };
}
