/**
 * Two returns; the first spreads a local an inner `if` may write over an earlier `by` (GH-161). Where the local holds
 * `by`, Laravel sends the title there.
 *
 * @see Workbench\App\Http\Resources\Spreads\BranchedLocalSpreadResource
 */
export interface BranchedLocalSpreadResource
{
    by?: { name: string | null; email: string | null };
    n?: number;
}
