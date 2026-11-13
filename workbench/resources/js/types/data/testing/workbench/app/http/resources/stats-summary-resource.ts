/**
 * A resource over a value object rather than a model, read through `$this->resource` and the proxy.
 *
 * @see Workbench\App\Http\Resources\StatsSummaryResource
 */
export interface StatsSummaryResource
{
    views: number;
    shares: number;
    summary: string;
}
