import type { VenueReview } from '.';

/** @see Workbench\App\Models\Venue */
export interface Venue
{
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface VenueRelations
{
    // Relations
    /** Reviews scoped to venues, via the subclass-only reviewable morph target */
    reviews: VenueReview[];
    // Counts
    reviews_count: number;
    // Exists
    reviews_exists: boolean;
}

export interface VenueAll extends Venue, VenueRelations {}
