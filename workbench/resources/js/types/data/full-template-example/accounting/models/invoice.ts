import { type AsEnum } from '@tolki/ts';

import { InvoiceStatus } from '../enums';
import type { User } from '../../app/models';
import type { InvoiceStatusType } from '../enums';
import type { Payment } from '.';

/**
 * @see Workbench\Accounting\Models\Invoice
 */
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
    // Mutators
    latest_payment: Payment | null;
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
