import { type AsEnum } from '@tolki/ts';

import { Currency, PaymentMethod } from '../../app/enums';
import { DueAtNotice, PaymentStatus } from '../enums';
import type { CurrencyType, PaymentMethodType } from '../../app/enums';
import type { DueAtNoticeType, PaymentStatusType } from '../enums';
import type { Invoice } from '.';

/**
 * @see Workbench\Accounting\Models\Payment
 */
export interface Payment
{
    // Columns
    id: number;
    invoice_id: number;
    status: PaymentStatusType;
    method: PaymentMethodType;
    currency: CurrencyType;
    amount: number;
    reference: string | null;
    paid_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    due_notice: DueAtNoticeType;
    // Relations
    invoice: Invoice;
    // Counts
    invoice_count: number;
    // Exists
    invoice_exists: boolean;
}

export interface PaymentResource extends Omit<Payment, 'status' | 'method' | 'currency' | 'due_notice'>
{
    status: AsEnum<typeof PaymentStatus>;
    method: AsEnum<typeof PaymentMethod>;
    currency: AsEnum<typeof Currency>;
    due_notice: AsEnum<typeof DueAtNotice>;
}
