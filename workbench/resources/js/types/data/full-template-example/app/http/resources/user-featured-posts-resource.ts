/**
 * A map over a relation loaded under a name the model does not declare, so nothing names what the local holds. The
 * map parameter's type names each element, and the trailing values()/all() keep that list.
 *
 * @see Workbench\App\Http\Resources\UserFeaturedPostsResource
 */
export interface UserFeaturedPostsResource
{
    id: number;
    featured_posts?: ({ id: number; title: string; file: string | null })[];
}
