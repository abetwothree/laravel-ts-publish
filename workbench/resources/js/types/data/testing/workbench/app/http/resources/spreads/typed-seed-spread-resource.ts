/**
 * Typed maps and shapes written whole into a local, given a key, then spread over earlier keys (GH-161 C2, C3, C4).
 *
 * @see Workbench\App\Http\Resources\Spreads\TypedSeedSpreadResource
 */
export interface TypedSeedSpreadResource
{
    tones_given_key_under_map_return: { by: { name: string | null; email: string | null }; z: number };
    shape_method_given_key: { by: { name: string | null; email: string | null }; z: number };
    map_property_given_key: { by: { name: string | null; email: string | null }; z: number };
    local_receiver_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    static_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    filtered_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    map_plus_assigned_key: { by: { name: string | null; email: string | null }; z: number };
    map_with_key_unset: { by: { name: string | null; email: string | null }; z: number };
    own_delegate_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    docblock_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    coalesced_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    helper_built_map_given_key: { by: string; z: number };
    shape_property_given_key: { by: { name: string | null; email: string | null }; z: number };
    collected_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    number_map_given_key_on_one_path: { by: { name: string | null; email: string | null }; z: number };
    map_over_earlier_tone: { tone: { name: string | null; email: string | null } };
    map_given_two_keys: { by: { name: string | null; email: string | null }; z: number };
    map_written_in_if_given_key: { by: { name: string | null; email: string | null }; z: number };
    string_map_given_key_on_one_path: { by: { name: string | null; email: string | null }; z: number };
    map_given_number_key_as_value: unknown;
    map_given_number_key_spread: { by: { name: string | null; email: string | null } };
    typed_open_local_over_keys: { by: { name: string | null; email: string | null }; z: number };
    top_tones_given_key: { by: { name: string | null; email: string | null }; z: number };
    top_map_over_earlier_tone: { tone: { name: string | null; email: string | null } };
    top_shape_method_given_key: { by: { name: string | null; email: string | null } };
    returned_map_given_number_key: { n: number };
    top_typed_open_local_over_keys: { by: { name: string | null; email: string | null }; z: number };
}
