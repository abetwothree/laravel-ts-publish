/**
 * Property chains read from a local variable that holds a model, with and without nullsafe steps.
 * Every expression is written twice, once through `$this->` and once through `$this->resource->`.
 *
 * @see Workbench\App\Http\Resources\ReceiverPropertyResource
 */
export interface ReceiverPropertyResource
{
    post_title: string | null;
    post_published_at: string | null;
    post_author_name: string | null;
    post_title_direct: string;
    resource_post_title: string | null;
    resource_post_published_at: string | null;
    resource_post_author_name: string | null;
    resource_post_title_direct: string;
    post_title_via_this: string | null;
    post_title_via_resource: string | null;
}
