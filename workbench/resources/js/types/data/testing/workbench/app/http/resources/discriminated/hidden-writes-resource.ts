/**
 * Literals whose discriminant a write changes between two of its reads, where the write check does not see it yet: a
 * by-reference argument, a callable a helper calls, a write before a key that drops a value, a call inside an arm, a
 * get hook, the model handed to a function, helpers that write two levels down or through the model, a closure a
 * helper calls, the model handed to another class's static method, a local closure's callable a helper calls, and the
 * resource handed second in a pair.
 * PHP can send a value no arm admits, so each literal should publish flat (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\HiddenWritesResource
 */
export interface HiddenWritesResource
{
    by_reference: { pinned: boolean; reset: boolean; detail: string | null };
    by_reference_in_helper: { pinned: boolean; reset: boolean; detail: string | null };
    callable_in_when: { is_pinned: boolean; cleared?: boolean; detail: string | null };
    write_before_dropper: { maybe: true; detail: string; reset: boolean; check: string } | { maybe: false; detail: null; reset: boolean; check: string };
    write_before_dropper_enum: { priority: 0 | 2 | 3; detail: string; reset: boolean; check: string } | { priority: 1; detail: null; reset: boolean; check: string };
    call_in_arm: { is_pinned: boolean; changed: string | null; detail: string | null };
    hooked: { flag: true; detail: string } | { flag: false; detail: number };
    handed_model: { is_pinned: boolean; reset: boolean; detail: string | null };
    two_helpers_down: { is_pinned: boolean; reset: boolean; detail: string | null };
    helper_fills_model: { is_pinned: boolean; reset: boolean; detail: string | null };
    closure_in_when: { is_pinned: boolean; cleared?: boolean; detail: string | null };
    static_writer: { is_pinned: boolean; reset: boolean; detail: string | null };
    local_callable_in_when: { is_pinned: boolean; cleared: boolean; detail: string | null };
    pair_holding_subject: { is_pinned: boolean; reset: boolean; detail: string | null };
}
