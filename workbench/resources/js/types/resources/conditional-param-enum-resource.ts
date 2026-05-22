import { type AsEnum } from '@tolki/enum';

import { Currency, OrderStatus, Role } from '../enums';

/** Exercises issue #38: closure parameter passed by the conditional method, where the return expression wraps an enum in EnumResource::make() or returns it bare. The bug: the analyzer resolves the return type as `unknown` instead of recognising the enum type from the param or the EnumResource wrapper. */
export interface ConditionalParamEnumResource
{
    id: number;
    status_resource?: AsEnum<typeof OrderStatus>;
    status_bare?: unknown;
    currency_resource?: AsEnum<typeof Currency>;
    user_role?: AsEnum<typeof Role>;
}
