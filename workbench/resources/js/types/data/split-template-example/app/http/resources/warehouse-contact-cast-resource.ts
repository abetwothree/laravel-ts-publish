import type { User } from '../../models';

/**
 * Casts away the CRM `User` of `contact` and both enums of the `review_priority` accessor, so the only class left to
 * import is the app's own `User`, under its own name.
 *
 * @see Workbench\App\Http\Resources\WarehouseContactCastResource
 */
export interface WarehouseContactCastResource
{
    id: number;
    manager: User | null;
    contact: { id: number; name: string } | null;
    review_priority: string;
}
