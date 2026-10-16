/**
 * A plain API resource declaring toAttributes() and no toArray(), which Laravel 12.45 and later sends.
 *
 * @see Workbench\App\Http\Resources\JsonApi\ToAttributesOnlyPlainResource
 */
export interface ToAttributesOnlyPlainResource
{
    headline: string;
}
