/**
 * `+` on arrays publishes PHP's array union, where the left operand's keys win and the right one adds the rest, and
 * `+` on numbers stays a number.
 *
 * @see Workbench\App\Http\Resources\HandoverUnionResource
 */
export interface HandoverUnionResource
{
    with_defaults: { note: string; id: number; sender_id: number | null };
    override: { code: string; lane: number };
    lanes: number[];
    chained: { a: number; b: string; c: boolean };
    total: number;
}
