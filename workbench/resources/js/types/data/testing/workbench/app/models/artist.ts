import type { ArtistReview, Label } from '.';

/** @see Workbench\App\Models\Artist */
export interface Artist
{
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ArtistRelations
{
    // Relations
    /** Reviews scoped to artists, via the subclass-only reviewable morph target */
    reviews: ArtistReview[];
    /** Labels attached via the custom Labelable pivot, which itself carries the morphTo back */
    labels: Label[];
    // Counts
    reviews_count: number;
    labels_count: number;
    // Exists
    reviews_exists: boolean;
    labels_exists: boolean;
}

export interface ArtistAll extends Artist, ArtistRelations {}
