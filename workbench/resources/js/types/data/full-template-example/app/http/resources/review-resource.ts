import type { ArtistResource, VenueResource } from '.';

/**
 * Exercises a morphTo closure parameter: $subject binds to every morph target, so toResource()
 * unions their resources and a plain attribute read unions the targets' own column types.
 *
 * @see Workbench\App\Http\Resources\ReviewResource
 */
export interface ReviewResource
{
    id: number;
    reviewable?: ArtistResource | VenueResource;
    reviewable_name?: string;
}
