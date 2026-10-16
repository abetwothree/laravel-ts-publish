/**
 * A JSON:API resource overriding toLinks() and toMeta().
 *
 * @see Workbench\App\Http\Resources\JsonApi\LinkedPostResource
 */
export interface LinkedPostResource
{
    id: string;
    type: 'linked_posts';
    attributes: { title: string };
    links?: { self: string };
    meta?: { readable: boolean; words: number | null };
}
