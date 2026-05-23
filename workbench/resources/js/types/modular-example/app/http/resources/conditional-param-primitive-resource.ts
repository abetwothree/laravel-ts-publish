/**
 * Exercises issue #38: closure parameter passed by the conditional method.
 * Each field uses a single-param arrow function that returns a scalar primitive.
 *
 * The bug: the analyzer resolves the return type of these closures as `unknown`
 * instead of inferring the scalar type from the return expression.
 */
export interface ConditionalParamPrimitiveResource
{
    id: number;
    user_name?: string;
    user_id?: number;
    user_verified?: boolean;
    notes_upper?: string;
    notes_length?: number;
}
