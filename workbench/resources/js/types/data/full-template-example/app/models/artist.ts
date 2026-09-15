import type { ArtistReview } from '.';

/** @see Workbench\App\Models\Artist */
export interface Artist
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** Reviews scoped to artists, via the subclass-only reviewable morph target */
    reviews: ArtistReview[];
    // Counts
    reviews_count: number;
    // Exists
    reviews_exists: boolean;
}
