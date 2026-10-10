/**
 * Numeric keys, which a resource's filter re-indexes into a list wherever every key of an array passes is_numeric(),
 * and keeps beside a named key.
 *
 * @see Workbench\App\Http\Resources\DepotTierResource
 */
export interface DepotTierResource
{
    id: number;
    tiers: string[];
    codes: string[];
    surcharges: number[];
    statuses: string[];
    labelled: { name: string; "42": boolean };
    nested: { inner: string[] };
    loaded_orders?: string[];
    "42": string;
}
