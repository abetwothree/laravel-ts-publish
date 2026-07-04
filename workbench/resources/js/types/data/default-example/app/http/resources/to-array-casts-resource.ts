import type { GeoPoint } from '@/types/geo';
import type { RoleType } from '../../enums';

/**
 * Fixture resource used to test #[TsCasts] placed on the toArray() method
 * rather than on the class. No class-level annotation is present on purpose so that
 * method-level behavior is tested in isolation.
 *
 * @see Workbench\App\Http\Resources\ToArrayCastsResource
 */
export interface ToArrayCastsResource
{
    id: number;
    name: string;
    email?: string | null;
    role: string;
    injected_field: Record<string, unknown>;
    coordinates: GeoPoint;
}
