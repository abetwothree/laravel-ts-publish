import type { Artist } from '.';

/**
 * Subclass of Review scoped to artist reviews — shares the reviews table via the inherited $table.
 *
 * @see Workbench\App\Models\ArtistReview
 */
export interface ArtistReview
{
    id: number;
    reviewable_type: string;
    reviewable_id: number;
    body: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ArtistReviewRelations
{
    // Relations
    /** Polymorphic parent (Venue or Artist, including their subclass-scoped review children) */
    reviewable: Artist;
    // Counts
    reviewable_count: number;
    // Exists
    reviewable_exists: boolean;
}

export interface ArtistReviewAll extends ArtistReview, ArtistReviewRelations {}
