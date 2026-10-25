import type { MediaTypeType } from '../../enums';

/** @see Workbench\App\Http\Resources\MediaTypeResource */
export interface MediaTypeResource
{
    name: 'Image' | 'Video' | 'Audio' | 'Document' | 'Archive';
    value: MediaTypeType;
    meta: { extensions: string[]; maxSizeMb: number; sizeUnit: string; icon: string };
}
