import { type AsEnum } from '@tolki/enum';

import { InvoiceStatus } from '../../enums';
import type { CurrencyType, PaymentMethodType } from '../../../app/enums';
import type { User } from '../../../app/models';
import type { DueAtNoticeType, PaymentStatusType } from '../../enums';
import type { Invoice } from '../../models';
import type { PaymentResource } from '.';

/** Exercises: when(cond, EnumResource::make) — conditional enum, cross-module whenLoaded bare (App\User), Resource::collection sibling, whenCounted, when(cond, value), mergeWhen. */
export interface InvoiceResource
{
    id: number;
    number: string;
    status?: AsEnum<typeof InvoiceStatus>;
    subtotal: number;
    tax: number;
    total: number;
    due_at: string | null;
    issued_at?: string | null;
    paid_at?: string | null;
    user?: User;
    payments?: PaymentResource[];
    payments_count?: number;
    notes?: string | null;
    latest_payment_only: { invoice_id: number; status: PaymentStatusType; method: PaymentMethodType; currency: CurrencyType; amount: number; reference: string | null; paid_at: string | null } | null;
    latest_payment_excluded: { id: number; created_at: string | null; updated_at: string | null; due_notice: DueAtNoticeType; invoice: Invoice } | null;
}
