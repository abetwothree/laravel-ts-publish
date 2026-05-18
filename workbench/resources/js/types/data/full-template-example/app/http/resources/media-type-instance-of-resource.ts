/**
 * @see Workbench\App\Http\Resources\MediaTypeInstanceOfResource
 */
export interface MediaTypeInstanceOfResource
{
    name: string;
    value: string;
    meta: { extensions: unknown[]; maxSizeMb: number; sizeUnit: string; icon: string };
}
