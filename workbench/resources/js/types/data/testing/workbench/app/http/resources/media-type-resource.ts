import type { MediaTypeKind, MediaTypeType } from '../../enums';

/** @see Workbench\App\Http\Resources\MediaTypeResource */
export interface MediaTypeResource
{
    name: MediaTypeKind;
    value: MediaTypeType;
    meta: { extensions: string[]; maxSizeMb: number; sizeUnit: string; icon: string };
}
