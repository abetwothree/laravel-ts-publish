import type { GeoBounds, GeoPoint } from '@/types/geo';

/**
 * Mailing address resource
 *
 * @see Workbench\App\Http\Resources\AddressResource
 */
export interface Address
{
    morphValue: string;
    id: number;
    label: string | null;
    line_1: string;
    line_2?: string | null;
    city: string;
    state: string | null;
    postal_code: string;
    country_code: string;
    latitude?: number | null;
    longitude?: number | null;
    is_default: boolean;
    user: { id: number; name: string };
    coordinates: GeoPoint;
    bounds: GeoBounds;
}
