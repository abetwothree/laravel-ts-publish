/**
 * Exercises userland global-helper reflection (route()), Carbon
 * receiver-method inference on a datetime-cast attribute, and the
 * can()/count() known-method rules (Task 11).
 *
 * `diff_result` and `period_result` are Carbon methods that return a Stringable value
 * object json_encode() never writes as its __toString(): a CarbonInterval writes
 * DateInterval's fields, and a CarbonPeriod the list of its dates.
 *
 * `to_mutable`/`to_immutable` are a Task 12 review follow-up: Carbon and
 * CarbonImmutable themselves are `string`, the ISO string their jsonSerialize() writes.
 *
 * `user_key`: `getKey()`'s type depends on which model it's called on, unlike
 * can()/cannot()/canAny() which are bool regardless of receiver. A resource's
 * `$request` is unbound, so `$request->user()` names no model and this stays
 * unknown rather than borrowing the Order this resource wraps.
 *
 * @see Workbench\App\Http\Resources\HelperCallResource
 */
export interface HelperCallResource
{
    route_url: string;
    ship_date: string;
    can_edit: boolean;
    item_total: number;
    diff_result: { y: number; m: number; d: number; h: number; i: number; s: number; f: number; invert: number; days: number | false; from_string: false };
    period_result: string[];
    to_mutable: string;
    to_immutable: string;
    user_key: unknown;
}
