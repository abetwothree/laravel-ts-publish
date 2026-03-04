export const MembershipLevel = {
    _cases: ['Free', 'Basic', 'Premium', 'Enterprise'],
    _methods: [],
    _static: [],
    Free: 'Free',
    Basic: 'Basic',
    Premium: 'Premium',
    Enterprise: 'Enterprise',
} as const;

export type MembershipLevelType = 'Free' | 'Basic' | 'Premium' | 'Enterprise';
