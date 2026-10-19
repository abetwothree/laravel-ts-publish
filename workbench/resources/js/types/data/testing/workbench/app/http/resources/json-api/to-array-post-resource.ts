/**
 * A JSON:API resource declaring toArray(), which toAttributes() falls back to, so its keys are the attributes.
 *
 * @see Workbench\App\Http\Resources\JsonApi\ToArrayPostResource
 */
export interface ToArrayPostResource
{
    id: string;
    type: 'to_array_posts';
    attributes: { title: string; rating: string | null };
}
