import { defineEnum } from '@tolki/ts';

/**
 * @see Workbench\Accounting\Enums\InvoiceStatus
 */
export const InvoiceStatus = defineEnum({
    Draft: 'draft',
    Sent: 'sent',
    Paid: 'paid',
    Overdue: 'overdue',
    Cancelled: 'cancelled',
    Void: 'void',
    backed: true,
    _cases: ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled', 'Void'],
} as const);

export type InvoiceStatusType = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled' | 'void';

export type InvoiceStatusKind = 'Draft' | 'Sent' | 'Paid' | 'Overdue' | 'Cancelled' | 'Void';
