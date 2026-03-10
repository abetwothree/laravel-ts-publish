export interface Image
{
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
}

export interface ImageMutators
{
    size_for_humans: string;
    is_landscape: boolean;
    aspect_ratio: string | null;
}

export interface ImageRelations
{
    // Relations
    imageable: Image;
    // Counts
    imageable_count: number;
    // Exists
    imageable_exists: boolean;
}

export interface ImageAll extends Image, ImageMutators, ImageRelations {}
