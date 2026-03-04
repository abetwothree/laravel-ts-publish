export const PaymentStatus = {
    _cases: ['Pending', 'Completed', 'Failed', 'Refunded'],
    _methods: [],
    _static: [],
    Pending: 'pending',
    Completed: 'completed',
    Failed: 'failed',
    Refunded: 'refunded',
} as const;

export type PaymentStatusType = 'pending' | 'completed' | 'failed' | 'refunded';

export type PaymentStatusKind = 'Pending' | 'Completed' | 'Failed' | 'Refunded';
