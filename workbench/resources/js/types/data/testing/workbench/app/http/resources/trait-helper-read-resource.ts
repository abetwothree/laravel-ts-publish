/**
 * Reads a trait-declared `: array` helper through three receivers: itself, a container instance and a static call.
 *
 * @see Workbench\App\Http\Resources\TraitHelperReadResource
 */
export interface TraitHelperReadResource
{
    id: number;
    own_quote: { band: string; ceiling: number };
    service_quote: { band: string; ceiling: number };
    static_quote: { band: string; ceiling: number };
}
