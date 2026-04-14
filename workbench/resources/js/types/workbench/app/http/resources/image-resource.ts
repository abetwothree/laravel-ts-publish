/**
 * Exercises: whenNotNull on multiple nullable columns.
 *
 * @see Workbench\App\Http\Resources\ImageResource
 */
export interface ImageResource
{
    id: number;
    url: string;
    alt_text: string | null;
    mime_type: string;
    size_bytes: number;
    width?: number | null;
    height?: number | null;
}
