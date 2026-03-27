import type { BaseResource } from '@/types/base';
import type { ResourceRoutes } from '@/types/resources';
import type { Routable } from '@/types/routing';
import type { Timestamps } from '@/types/util';

/** Resource with no @mixin or TsResource — tests convention-based model guess. Also tests multiple TsExtends in parent class, trait, and locally. */
export interface WarehouseResource extends BaseResource, ExtendableInterface, Omit<Timestamps, "created_at" | "updated_at">, ResourceRoutes, Pick<Routable, "store" | "update">
{
    id: number;
    name: string;
}
