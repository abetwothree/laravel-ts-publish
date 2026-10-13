/**
 * Locals a by-reference built-in rewrites in a form that keeps their type: each sort of the form it keeps, a count, a
 * push, pop, shift or splice of a list, a walk or a by-reference loop writing each element within its type, and a
 * recursive walk or a sort of a key likewise. A resource re-indexes a list a key-keeping sort reorders.
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
    walked_nested: { a: { b: number } };
    sorted_member: { x: number[] };
    nested_sorted_member: { x: { y: number[] } };
    key_sorted_member: { x: { b: number; a: number } };
    looped: string[];
    popped: string[];
    shifted: string[];
    spliced: string[];
    value_sorted_list: string[];
    value_sorted_member: { x: string[] };
    mixed_member: { x: string[]; c: { "3": string } };
    walked_keys: { a: string };
    walked_import: string[];
    pre_written_loop: string[];
    concat_walked: string[];
    plus_walked: number[];
    copied_before_sort: { k: number };
    list_copied_after_sort: string[];
}
