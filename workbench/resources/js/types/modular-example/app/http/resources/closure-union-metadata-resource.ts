import type { OrderStatusType } from '../../enums';
import type { OrderItem } from '../../models';
import type { TagResource } from '.';

/** Exercises analyzeClosureUnion metadata propagation (enum, model, resource FQCNs) and analyzeRelatedModelMethodCall fallback (line 451). */
export interface ClosureUnionMetadataResource
{
    id: number;
    status_or_null?: OrderStatusType | null;
    nested_or_null?: TagResource | null;
    user_titled?: string;
    detail_or_null?: { tag: TagResource; name: string } | null;
    items_or_null?: OrderItem[] | null;
}
