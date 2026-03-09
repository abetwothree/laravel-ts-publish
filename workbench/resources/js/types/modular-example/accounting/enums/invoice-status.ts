import { defineEnum } from '@tolki/enum';

export const InvoiceStatus = defineEnum({
    Draft: 'draft',
    Sent: 'sent',
    Paid: 'paid',
    Overdue: 'overdue',
    Cancelled: 'cancelled',
    Void: 'void',
    _cases: ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled', 'Void'],
    _methods: [],
    _static: [],
} as const);

export type InvoiceStatusType = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled' | 'void';

export type InvoiceStatusKind = 'Draft' | 'Sent' | 'Paid' | 'Overdue' | 'Cancelled' | 'Void';
