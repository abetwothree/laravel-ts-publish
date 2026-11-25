/**
 * Locals written and read inside one block, some of which then exit, each in a helper's returns (GH-161 B4, B5).
 *
 * @see Workbench\App\Http\Resources\Spreads\BlockReadSpreadResource
 */
export interface BlockReadSpreadResource
{
    one_return_after_block: { n?: number; by?: { name: string | null; email: string | null } };
    unconditional_write_in_block: { by?: { name: string | null; email: string | null }; n?: number };
    nested_read_in_block: { row?: { by: { name: string | null; email: string | null } }; n?: number };
    read_in_else: { n?: number; by?: { name: string | null; email: string | null } };
    read_in_elseif: { n?: number; by?: { name: string | null; email: string | null } };
    read_in_switch_case: { by?: { name: string | null; email: string | null }; n?: number };
    read_in_try: { by?: { name: string | null; email: string | null }; n?: number };
    read_two_deep: { by?: { name: string | null; email: string | null }; n?: number };
    read_in_when_closure?: { by: { name: string | null; email: string | null } };
    read_in_loop_return: { by?: { name: string | null; email: string | null }; n?: number };
    read_in_switch_one_path: { by: { name: string | null; email: string | null } };
    read_in_try_rethrow: { by: { name: string | null; email: string | null } };
    value_read_in_writing_block: { early?: unknown; n?: number };
    maybe_local_value_in_block: { row?: unknown; n?: number };
    condition_rewrites_local: { v?: unknown; n?: number };
    emptiness_guard_throws: { row?: Record<string, number> | never[]; n?: number };
    emptiness_guard_returns: { n?: number; row?: Record<string, number> | never[] };
    conditional_unset_in_block: { row?: unknown; n?: number };
    key_write_in_exiting_block: { by?: { name: string | null; email: string | null }; n?: number };
    exiting_key_write_nested: { row?: { by: { name: string | null; email: string | null } }; n?: number };
    exiting_maybe_key_write: { by?: { name: string | null; email: string | null }; n?: number };
    whole_write_nested_in_exiting_block: { early?: { a: number }; late?: { a: number } };
    nested_whole_write_then_spread: { by?: { name: string | null; email: string | null }; n?: number };
}
