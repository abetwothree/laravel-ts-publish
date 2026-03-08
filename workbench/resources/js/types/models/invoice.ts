import { InvoiceStatusType } from '../enums';
import { Payment, User } from './';

export interface Invoice
{
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
}

export interface InvoiceRelations
{
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

export interface InvoiceAll extends Invoice, InvoiceRelations {}
