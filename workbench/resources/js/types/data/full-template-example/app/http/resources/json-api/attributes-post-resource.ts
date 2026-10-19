/**
 * A JSON:API resource whose attributes come from a toAttributes() override, one of them conditional.
 *
 * @see Workbench\App\Http\Resources\JsonApi\AttributesPostResource
 */
export interface AttributesPostResource
{
    id: string;
    type: 'attributes_posts';
    attributes: { title: string; word_count: number | null; is_long: boolean; secret?: string };
}
