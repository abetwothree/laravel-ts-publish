import type { ResourceRoutes } from '@/types/resources';
import type { Routable } from '@/types/routing';

/**
 * @see Workbench\App\Http\Resources\RoutableResource
 */
export interface RoutableResource extends ResourceRoutes, Pick<Routable, "store" | "update">
{
}
