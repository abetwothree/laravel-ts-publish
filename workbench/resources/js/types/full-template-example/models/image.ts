export interface Image
{
    // Columns
    id: number;
    imageable_type: string;
    imageable_id: number;
    url: string;
    alt_text: string | null;
    disk: string;
    path: string;
    mime_type: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    sort_order: number;
    metadata: unknown[] | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** Human-readable file size */
    size_for_humans: string;
    /** Whether the image is landscape orientation */
    is_landscape: boolean;
    /** Aspect ratio as a string (e.g. "16:9") or null if dimensions not set */
    aspect_ratio: string | null;
    extension: string | null;
    /** This is the size test to parse from the docblock in the test for accessor type resolution. */
    size: number;
    // Relations
    /** Polymorphic parent (Product, Post, User, etc.) */
    imageable: Image;
    // Counts
    imageable_count: number;
    // Exists
    imageable_exists: boolean;
}
