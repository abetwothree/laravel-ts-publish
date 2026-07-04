import { type AsEnum } from '@tolki/ts';

import { OrderStatus } from '../../enums';
import type { UserResource } from '.';

/**
 * Exercises issue #38 using non-arrow (full) closures with a parameter.
 * Covers primitives, arrays, resources, enums, and guard-clause patterns —
 * all using `function ($param) { return ...; }` syntax rather than arrow fns.
 *
 * The bug: the analyzer resolves the return type of these closures as `unknown`
 * regardless of the return expression when a parameter is present.
 *
 * @see Workbench\App\Http\Resources\ConditionalParamFullClosureResource
 */
export interface ConditionalParamFullClosureResource
{
    id: number;
    user_name?: string;
    user_summary?: { id: number; email: string };
    items_mapped?: { id: number; name: string; quantity: number }[];
    user_resource?: UserResource;
    status_resource?: AsEnum<typeof OrderStatus>;
    shipping_safe?: { name: string; email: string } | null;
}
