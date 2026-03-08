import { defineEnum } from '@tolki/enum';

export const MembershipLevel = defineEnum({
    Free: 'Free',
    Basic: 'Basic',
    Premium: 'Premium',
    Enterprise: 'Enterprise',
    _cases: ['Free', 'Basic', 'Premium', 'Enterprise'],
    _methods: [],
    _static: [],
} as const);

export type MembershipLevelType = 'Free' | 'Basic' | 'Premium' | 'Enterprise';
