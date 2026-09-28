import type { Artist, Venue } from '.';

/** @see Workbench\App\Models\Review */
export interface Review
{
    id: number;
    reviewable_type: string;
    reviewable_id: number;
    body: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ReviewRelations
{
    // Relations
    /** Polymorphic parent (Venue or Artist, including their subclass-scoped review children) */
    reviewable: Artist | Venue;
    // Counts
    reviewable_count: number;
    // Exists
    reviewable_exists: boolean;
}

export interface ReviewAll extends Review, ReviewRelations {}
