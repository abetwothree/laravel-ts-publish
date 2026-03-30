import type { ResourceRoutes } from '@/types/resources';
import type { Routable } from '@/types/routing';

export interface RoutableResource extends ResourceRoutes, Pick<Routable, "store" | "update">
{
}
