/**
 * Literals whose discriminant a call changes through a binding the write check does not see yet: a reference a
 * string-keyed unpack hands a parameter before its position, a static call through `$this::`, a callable pair a
 * helper returns with its keys out of order, and a local handed by reference to an object no scan names. PHP can
 * send a value no arm admits, so each literal should publish flat (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\DispatchWritesResource
 */
export interface DispatchWritesResource
{
    named_unpack: { pinned: boolean; reset: boolean; detail: string | null };
    static_through_this: { pinned: boolean; reset: boolean; detail: string | null };
    pair_keys_out_of_order: { is_pinned: boolean; reset: boolean; detail: string | null };
    local_by_reference: { flag: boolean; reset: boolean; detail: string | null };
}
