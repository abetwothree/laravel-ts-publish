import { defineEnum } from '@tolki/enum';

export const DueAtNotice = defineEnum({
    ComingUp: 'Payment due date is coming up',
    DueToday: 'Payment is due today',
    PastDue: 'Payment is past due',
    backed: true,
    _cases: ['ComingUp', 'DueToday', 'PastDue'],
} as const);

export type DueAtNoticeType = 'Payment due date is coming up' | 'Payment is due today' | 'Payment is past due';

export type DueAtNoticeKind = 'ComingUp' | 'DueToday' | 'PastDue';
