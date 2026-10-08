/**
 * An aggregate whose alias the parent model casts: a `timestamp` cast writes the Unix integer.
 *
 * @see Workbench\App\Http\Resources\ProductAggregateCastResource
 */
export interface ProductAggregateCastResource
{
    first_sold_ts?: number | null;
}
