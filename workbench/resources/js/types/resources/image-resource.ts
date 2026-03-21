/** Exercises: whenNotNull on multiple nullable columns. */
export interface ImageResource
{
    id: number;
    url: string;
    alt_text: string | null;
    mime_type: string;
    size_bytes: number;
    width?: number;
    height?: number;
}
