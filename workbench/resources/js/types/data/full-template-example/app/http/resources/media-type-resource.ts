/** @see Workbench\App\Http\Resources\MediaTypeResource */
export interface MediaTypeResource
{
    name: string;
    value: string;
    meta: { extensions: string[]; maxSizeMb: number; sizeUnit: string; icon: string };
}
