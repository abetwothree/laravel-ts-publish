/**
 * A `when()` key spread over an earlier `by`, which InertiaPropsWalkController resolves (GH-161 A9, C3).
 *
 * @see Workbench\App\Http\Resources\Spreads\WhenKeySpreadResource
 */
export interface WhenKeySpreadResource
{
    id: number;
    by?: string;
}
