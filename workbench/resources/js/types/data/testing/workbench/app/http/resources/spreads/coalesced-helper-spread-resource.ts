/**
 * A helper whose local may leave `by` unset, defaulted by `??=`, spread over an earlier `by` (GH-161). The helper
 * always writes `by`, so Laravel sends the title or `'z'`, never the author.
 *
 * @see Workbench\App\Http\Resources\Spreads\CoalescedHelperSpreadResource
 */
export interface CoalescedHelperSpreadResource
{
    id: number;
    by: { name: string | null; email: string | null };
}
