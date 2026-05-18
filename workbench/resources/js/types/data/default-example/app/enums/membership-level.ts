import { defineEnum } from '@tolki/ts';

/**
 * Pure unit enum with no attributes and no methods. Tests the absolute minimal enum path.
 *
 * @see Workbench\App\Enums\MembershipLevel
 */
export const MembershipLevel = defineEnum({
    Free: 'Free',
    Basic: 'Basic',
    Premium: 'Premium',
    Enterprise: 'Enterprise',
    backed: false,
    _cases: ['Free', 'Basic', 'Premium', 'Enterprise'],
} as const);

export type MembershipLevelType = 'Free' | 'Basic' | 'Premium' | 'Enterprise';
