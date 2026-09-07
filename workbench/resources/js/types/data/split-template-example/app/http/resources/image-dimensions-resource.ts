/**
 * Both when() arms are inline objects whose members are nullable, so the union must be split at the
 * top level only.
 *
 * @see Workbench\App\Http\Resources\ImageDimensionsResource
 */
export interface ImageDimensionsResource
{
    id: number;
    box: { width: number | null; height: number | null } | { width: number | null };
}
