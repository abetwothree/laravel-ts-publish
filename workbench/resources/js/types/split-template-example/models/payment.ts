import { type AsEnum } from '@tolki/enum';

import type { Currency, CurrencyType, DueAtNotice, DueAtNoticeType, PaymentMethod, PaymentMethodType, PaymentStatus, PaymentStatusType } from '../enums';
import type { Invoice } from './';

export interface Payment
{
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
}

export interface PaymentResource extends Omit<Payment, 'status' | 'method' | 'currency'>
{
    status: AsEnum<typeof PaymentStatus>;
    method: AsEnum<typeof PaymentMethod>;
    currency: AsEnum<typeof Currency>;
}

export interface PaymentMutators
{
    due_notice: DueAtNoticeType;
}

export interface PaymentMutatorsResource extends Omit<PaymentMutators, 'due_notice'>
{
    due_notice: AsEnum<typeof DueAtNotice>;
}

export interface PaymentRelations
{
    // Relations
    invoice: Invoice;
    // Counts
    invoice_count: number;
    // Exists
    invoice_exists: boolean;
}

export interface PaymentAll extends Payment, PaymentMutators, PaymentRelations {}

export interface PaymentAllResource extends PaymentResource, PaymentMutatorsResource, PaymentRelations {}
