import { defineEnum } from '@tolki/enum';

export const MembershipLevel = defineEnum({
    Free: 'Free',
    Basic: 'Basic',
    Premium: 'Premium',
    Enterprise: 'Enterprise',
    _cases: ['Free', 'Basic', 'Premium', 'Enterprise'],
} as const);

export type MembershipLevelType = 'Free' | 'Basic' | 'Premium' | 'Enterprise';
