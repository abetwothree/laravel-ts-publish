import type { OrderItemResource, UserResource } from '.';

/**
 * Exercises issue #38: closure parameter passed by the conditional method,
 * where the return expression is a JsonResource make() or collection() call.
 *
 * The bug: the analyzer resolves the return type as `unknown` instead of
 * inferring the resource type (e.g. UserResource or OrderItemResource[]).
 *
 * @see Workbench\App\Http\Resources\ConditionalParamJsonResourceResource
 */
export interface ConditionalParamJsonResourceResource
{
    id: number;
    user?: UserResource;
    items?: OrderItemResource[];
    user_when?: UserResource;
}
