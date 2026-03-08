import { defineEnum } from '@tolki/enum';

export const PaymentStatus = defineEnum({
    Pending: 'pending',
    Completed: 'completed',
    Failed: 'failed',
    Refunded: 'refunded',
    _cases: ['Pending', 'Completed', 'Failed', 'Refunded'],
    _methods: [],
    _static: [],
} as const);

export type PaymentStatusType = 'pending' | 'completed' | 'failed' | 'refunded';

export type PaymentStatusKind = 'Pending' | 'Completed' | 'Failed' | 'Refunded';
