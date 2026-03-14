import { type AsEnum } from '@tolki/enum';

import type { InvoiceStatus, InvoiceStatusType } from '../enums';
import type { Payment, User } from './';

export interface Invoice
{
    // Columns
    id: number;
    user_id: number;
    number: string;
    status: InvoiceStatusType;
    subtotal: number;
    tax: number;
    total: number;
    due_at: string | null;
    issued_at: string | null;
    paid_at: string | null;
    notes: string | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    user: User;
    payments: Payment[];
    // Counts
    user_count: number;
    payments_count: number;
    // Exists
    user_exists: boolean;
    payments_exists: boolean;
}

export interface InvoiceResource extends Omit<Invoice, 'status'>
{
    status: AsEnum<typeof InvoiceStatus>;
}
