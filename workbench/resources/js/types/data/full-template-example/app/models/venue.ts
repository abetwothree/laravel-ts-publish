import type { VenueReview } from '.';

/** @see Workbench\App\Models\Venue */
export interface Venue
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** Reviews scoped to venues, via the subclass-only reviewable morph target */
    reviews: VenueReview[];
    // Counts
    reviews_count: number;
    // Exists
    reviews_exists: boolean;
}
