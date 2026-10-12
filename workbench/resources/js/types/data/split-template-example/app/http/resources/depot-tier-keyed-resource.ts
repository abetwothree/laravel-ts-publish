/**
 * The same numeric keys in a resource that preserves its keys, so every array keeps them.
 *
 * @see Workbench\App\Http\Resources\DepotTierKeyedResource
 */
export interface DepotTierKeyedResource
{
    id: number;
    tiers: { "1": string; "2": string };
    codes: { "06": string; "1.5": string };
    surcharges: { "3": number };
    statuses: { "200": string; "404": string };
    labelled: { name: string; "42": boolean };
    nested: { inner: { "7": string } };
    loaded_orders?: { "10": string; "20": string };
    "42": string;
}
