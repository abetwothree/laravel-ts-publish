import type { ArtistResource, VenueResource } from '.';

/**
 * Exercises a morphTo closure parameter: $subject binds to every morph target, so toResource()
 * unions their resources and a plain attribute read unions the targets' own column types. The
 * closure-less toResource() unions the same resources, and its `?->` spelling adds null.
 *
 * @see Workbench\App\Http\Resources\ReviewResource
 */
export interface ReviewResource
{
    id: number;
    reviewable?: ArtistResource | VenueResource;
    reviewable_name?: string;
    reviewable_resource: ArtistResource | VenueResource;
    reviewable_nullsafe_resource: ArtistResource | VenueResource | null;
}
