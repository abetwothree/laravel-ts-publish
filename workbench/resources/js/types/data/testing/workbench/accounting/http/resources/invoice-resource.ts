import { type AsEnum } from '@tolki/ts';

import { InvoiceStatus } from '../../enums';
import type { User } from '../../../app/models';
import type { Payment } from '../../models';
import type { PaymentResource } from '.';

/**
 * Exercises: when(cond, EnumResource::make) — conditional enum, cross-module
 * whenLoaded bare (App\User), Resource::collection sibling, whenCounted,
 * when(cond, value), mergeWhen.
 *
 * @see Workbench\Accounting\Http\Resources\InvoiceResource
 */
export interface InvoiceResource
{
    id: number;
    number: string;
    status?: AsEnum<typeof InvoiceStatus>;
    subtotal: string;
    tax: string;
    total: string;
    due_at: string | null;
    issued_at?: string;
    paid_at?: string | null;
    user?: User;
    payments?: PaymentResource[];
    payments_count?: number;
    notes?: string | null;
    latest_payment_only: Pick<Payment, 'invoice_id' | 'status' | 'method' | 'currency' | 'amount' | 'reference' | 'paid_at'> | null;
    latest_payment_excluded: Pick<Payment, 'id' | 'created_at' | 'updated_at'> | null;
}
