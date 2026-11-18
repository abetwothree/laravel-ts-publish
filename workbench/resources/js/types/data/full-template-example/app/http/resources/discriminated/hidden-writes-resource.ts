/**
 * Literals whose discriminant a write changes between two of its reads, where the write check does not see it yet: a
 * by-reference argument, a callable a helper calls, a write before a key that drops a value, a call inside an arm, a
 * get hook, the model handed to a function, and helpers that write two levels down or through the model. PHP can send
 * a value no arm admits, so each literal should publish flat (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\HiddenWritesResource
 */
export interface HiddenWritesResource
{
    by_reference: { pinned: true; reset: boolean; detail: string } | { pinned: false; reset: boolean; detail: null };
    by_reference_in_helper: { pinned: true; reset: boolean; detail: string } | { pinned: false; reset: boolean; detail: null };
    callable_in_when: { is_pinned: true; cleared?: boolean; detail: string } | { is_pinned: false; cleared?: boolean; detail: null };
    write_before_dropper: { maybe: true; detail: string; reset: boolean; check: string } | { maybe: false; detail: null; reset: boolean; check: string };
    write_before_dropper_enum: { priority: 0 | 2 | 3; detail: string; reset: boolean; check: string } | { priority: 1; detail: null; reset: boolean; check: string };
    call_in_arm: { is_pinned: true; changed: string; detail: string } | { is_pinned: false; changed: null; detail: null };
    hooked: { flag: true; detail: string } | { flag: false; detail: number };
    handed_model: { is_pinned: true; reset: boolean; detail: string } | { is_pinned: false; reset: boolean; detail: null };
    two_helpers_down: { is_pinned: true; reset: boolean; detail: string } | { is_pinned: false; reset: boolean; detail: null };
    helper_fills_model: { is_pinned: true; reset: boolean; detail: string } | { is_pinned: false; reset: boolean; detail: null };
}
