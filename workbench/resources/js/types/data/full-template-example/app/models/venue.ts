import type { Label, VenueReview } from '.';

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
    /** Labels attached via the custom Labelable pivot, which itself carries the morphTo back */
    labels: Label[];
    // Counts
    reviews_count: number;
    labels_count: number;
    // Exists
    reviews_exists: boolean;
    labels_exists: boolean;
}
