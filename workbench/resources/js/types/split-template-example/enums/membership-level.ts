import { defineEnum } from '@tolki/enum';

/** Pure unit enum with no attributes and no methods. Tests the absolute minimal enum path. */
export const MembershipLevel = defineEnum({
    Free: 'Free',
    Basic: 'Basic',
    Premium: 'Premium',
    Enterprise: 'Enterprise',
    _cases: ['Free', 'Basic', 'Premium', 'Enterprise'],
} as const);

export type MembershipLevelType = 'Free' | 'Basic' | 'Premium' | 'Enterprise';
