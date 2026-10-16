/**
 * A JSON:API resource whose toAttributes() returns a variable: each keyless name in it reads the model attribute.
 *
 * @see Workbench\App\Http\Resources\JsonApi\VariableAttributesPostResource
 */
export interface VariableAttributesPostResource
{
    id: string;
    type: 'variable_attributes_posts';
    attributes: { title: string; word_count: number | null; content?: string; is_long: boolean };
}
