/**
 * Exercises issue #38: the exact bug pattern from the issue report.
 * A closure receives the loaded relation as a parameter and calls ->map()
 * with a nested inner closure that returns an array shape.
 *
 * The bug: the outer closure param return type resolves to `unknown` instead of
 * inferring the mapped array shape `{ id: number; name: string; quantity: number }[]`.
 */
export interface ConditionalParamMappedResource
{
    id: number;
    items_mapped?: { id: number; name: string; quantity: number }[];
    items_priced?: { id: number; sku: string; unit_price: number; total_price: number }[];
    item_names?: string[];
}
