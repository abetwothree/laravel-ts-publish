/**
 * A vague `: array` helper reached two ways — through a container instance and as a static call — so
 * both reflection sites fall back to the literal body.
 *
 * @see Workbench\App\Http\Resources\ServiceReturnResource
 */
export interface ServiceReturnResource
{
    quote: { unit: string; minimum: number; discounted: { unit: string } };
    tiers: { "1": string; "2": string };
}
