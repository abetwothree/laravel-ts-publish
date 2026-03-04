export const InvoiceStatus = {
    _cases: ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled', 'Void'],
    _methods: [],
    _static: [],
    Draft: 'draft',
    Sent: 'sent',
    Paid: 'paid',
    Overdue: 'overdue',
    Cancelled: 'cancelled',
    Void: 'void',
} as const;

export type InvoiceStatusType = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled' | 'void';

export type InvoiceStatusKind = 'Draft' | 'Sent' | 'Paid' | 'Overdue' | 'Cancelled' | 'Void';
