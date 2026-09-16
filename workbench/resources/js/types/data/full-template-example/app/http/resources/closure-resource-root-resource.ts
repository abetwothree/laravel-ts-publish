/**
 * Reads `$this->resource` inside whenLoaded closures bound to a different relation's model, so each
 * chain must root at the resource's own model rather than at the closure's relation model.
 *
 * @see Workbench\App\Http\Resources\ClosureResourceRootResource
 */
export interface ClosureResourceRootResource
{
    published_outside: string | null;
    published_inside?: string | null;
    title_inside?: string;
    class_inside?: string | null;
    author_name_outside: string;
    author_name_inside?: string;
    author_name_nullsafe_inside?: string | null;
    author_titled_outside: string | null;
    author_titled_inside?: string | null;
    options_inside?: Record<string, string> | null;
    profile_bio_inside?: string | null;
}
