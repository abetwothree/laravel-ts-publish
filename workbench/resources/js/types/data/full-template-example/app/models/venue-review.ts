import type { Venue } from '.';

/**
 * Subclass of Review scoped to venue reviews — shares the reviews table via the inherited $table.
 *
 * @see Workbench\App\Models\VenueReview
 */
export interface VenueReview
{
    // Columns
    id: number;
    reviewable_type: string;
    reviewable_id: number;
    body: string;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** Polymorphic parent (Venue or Artist, including their subclass-scoped review children) */
    reviewable: Venue;
    // Counts
    reviewable_count: number;
    // Exists
    reviewable_exists: boolean;
}
