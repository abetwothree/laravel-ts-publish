import { type AsEnum } from '@tolki/ts';

import { Currency } from '../../../app/enums';
import { PaymentStatus } from '../../enums';
import type { PaymentMethodType } from '../../../app/enums';

/**
 * Exercises: multiple EnumResource::make from different namespaces (PaymentStatus, Currency from App), whenHas on PaymentMethod enum attribute, whenNotNull.
 *
 * @see Workbench\Accounting\Http\Resources\PaymentResource
 */
export interface PaymentResource
{
    id: number;
    status: AsEnum<typeof PaymentStatus>;
    currency: AsEnum<typeof Currency>;
    amount: number;
    method?: PaymentMethodType;
    reference?: string | null;
    paid_at?: string | null;
}
