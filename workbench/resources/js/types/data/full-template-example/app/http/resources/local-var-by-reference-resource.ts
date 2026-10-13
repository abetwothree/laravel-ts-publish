/**
 * Locals a by-reference built-in rewrites in a form that keeps their type: a sort of a list, a key sort of anything, a
 * key-keeping sort of an object, a count out-parameter, a push of the list's own element type, and a walk or a
 * by-reference loop that writes each element within its type.
 *
 * @see Workbench\App\Http\Resources\LocalVarByReferenceResource
 */
export interface LocalVarByReferenceResource
{
    sorted: string[];
    reversed: number[];
    user_sorted: string[];
    plucked: string[];
    key_sorted: { b: number; a: number };
    key_sorted_list: string[];
    user_key_sorted: { b: number; a: number };
    reverse_key_sorted: { a: number; b: number };
    value_sorted: { b: number; a: number };
    reverse_value_sorted: { a: number; b: number };
    user_value_sorted: { b: number; a: number };
    natural_sorted: { b: string; a: string };
    natural_case_sorted: { b: string; a: string };
    replaced_count: number;
    case_replaced_count: number;
    pattern_count: number;
    callback_count: number;
    pushed: string[];
    unshifted: string[];
    walked: { x: number };
    walked_list: string[];
    read_only_walk: { b: number };
    looped: string[];
}
