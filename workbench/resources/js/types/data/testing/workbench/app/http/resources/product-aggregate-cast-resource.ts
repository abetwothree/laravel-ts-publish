/**
 * Aggregates whose alias the parent model casts: `decimal:2` writes a string, and `timestamp` the Unix integer.
 *
 * @see Workbench\App\Http\Resources\ProductAggregateCastResource
 */
export interface ProductAggregateCastResource
{
    first_sold_ts?: number | null;
    unit_price_total?: string | null;
}
