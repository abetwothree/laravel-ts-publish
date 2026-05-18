import type { GeoPoint } from '@/types/geo';

/**
 * Exercises: parent spread inheriting customImports from parent trait TsResourceCasts.
 *
 * @see Workbench\App\Http\Resources\ExtendedAddressResource
 */
export interface ExtendedAddressResource
{
    id: number;
    computed: string;
    date_val: string;
    custom_val: CustomObject;
    plain: unknown;
    basic: unknown;
    firstName: string;
    lastName: string;
    isActive: boolean;
    location: GeoPoint;
    flag?: string | null;
    extra: Record<string, unknown>;
    extra_field: unknown;
}
