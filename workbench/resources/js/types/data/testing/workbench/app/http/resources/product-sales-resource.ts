/**
 * Exercises: whenAggregated() typed from the aggregated column and the connection's driver (SQLite here), its null arm
 * over no rows, a value closure passed the aggregate, and whenCounted(), whose count is never null.
 *
 * @see Workbench\App\Http\Resources\ProductSalesResource
 */
export interface ProductSalesResource
{
    id: string;
    last_sold_at?: string | null;
    first_item_name?: string | null;
    average_quantity?: number | null;
    top_quantity?: { max: number } | null;
    has_bulk_line?: boolean | null;
    revenue: number | null;
    has_items?: boolean;
}
