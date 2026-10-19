/**
 * A JSON:API resource overriding toId() with an int, which Laravel sends as a string, and toType() with a literal.
 *
 * @see Workbench\App\Http\Resources\JsonApi\IdentifiedPostResource
 */
export interface IdentifiedPostResource
{
    id: string;
    type: 'articles';
    attributes: { title: string };
}
