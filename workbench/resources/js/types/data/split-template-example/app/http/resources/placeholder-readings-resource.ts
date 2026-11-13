/**
 * Placeholders a dropped arm leaves: an empty list, and a null among out-of-order int keys (GH-154).
 *
 * @see Workbench\App\Http\Resources\PlaceholderReadingsResource
 */
export interface PlaceholderReadingsResource
{
    fills: { f: never[] | number[] }[];
    pairs: [string | null, number][];
}
