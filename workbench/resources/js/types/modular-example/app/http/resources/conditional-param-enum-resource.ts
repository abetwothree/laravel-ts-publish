/** Exercises issue #38: closure parameter passed by the conditional method, where the return expression wraps an enum in EnumResource::make() or returns it bare. The bug: the analyzer resolves the return type as `unknown` instead of recognising the enum type from the param or the EnumResource wrapper. */
export interface ConditionalParamEnumResource
{
    id: number;
    status_resource?: unknown;
    status_bare?: unknown;
    currency_resource?: unknown;
    user_role?: unknown;
}
