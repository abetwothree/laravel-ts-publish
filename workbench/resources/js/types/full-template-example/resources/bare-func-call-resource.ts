import type { GeoPoint } from '@/types/geo';

/** Fixture resource exercising bare function call spreads (without $this->). */
export interface BareFuncCallResource
{
    morphValue: string;
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
}
