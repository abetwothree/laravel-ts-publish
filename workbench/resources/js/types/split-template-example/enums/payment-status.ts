import { defineEnum } from '@tolki/enum';

export const PaymentStatus = defineEnum({
    Pending: 'pending',
    Completed: 'completed',
    Failed: 'failed',
    Refunded: 'refunded',
    backed: true,
    _cases: ['Pending', 'Completed', 'Failed', 'Refunded'],
} as const);

export type PaymentStatusType = 'pending' | 'completed' | 'failed' | 'refunded';

export type PaymentStatusKind = 'Pending' | 'Completed' | 'Failed' | 'Refunded';
