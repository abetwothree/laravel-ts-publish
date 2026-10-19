/**
 * A JSON:API resource sending links and meta through the defaults toLinks() and toMeta() return.
 *
 * @see Workbench\App\Http\Resources\JsonApi\DefaultLinksPostResource
 */
export interface DefaultLinksPostResource
{
    id: string;
    type: 'default_links_posts';
    attributes: { title: string };
    links?: { describedby: string };
    meta?: { version: number; stable: boolean };
}
