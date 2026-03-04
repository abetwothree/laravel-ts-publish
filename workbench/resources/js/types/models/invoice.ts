import { InvoiceStatusType } from '../enums';
import { User, Payment } from './';

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
    user: User;
    payments: Payment[];
}

export interface InvoiceRelationCounts
{
    user_count: number;
    payments_count: number;
}

export interface InvoiceRelationExists
{
    user_exists: boolean;
    payments_exists: boolean;
}
