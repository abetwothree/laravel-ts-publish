import type { MediaTypeKind, MediaTypeType } from '../../enums';

/** @see Workbench\App\Http\Resources\MediaTypeInstanceOfResource */
export interface MediaTypeInstanceOfResource
{
    name?: MediaTypeKind;
    value?: MediaTypeType;
    meta?: { extensions: string[]; maxSizeMb: number; sizeUnit: string; icon: string };
}
