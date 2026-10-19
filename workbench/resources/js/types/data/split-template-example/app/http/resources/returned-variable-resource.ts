/**
 * Builds its payload in a local variable and returns it: a key written on one path only publishes optional.
 *
 * @see Workbench\App\Http\Resources\ReturnedVariableResource
 */
export interface ReturnedVariableResource
{
    id: number;
    name: string;
    slug?: string;
    posts_count?: number;
    quote: { unit: string; tax: number };
}
