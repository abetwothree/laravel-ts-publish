import { defineEnum } from '@tolki/ts';

/**
 * Publishes the type `ClearanceType`, the name Crm's ClearanceType enum publishes its const under.
 *
 * @see Workbench\App\Enums\Clearance
 */
export const Clearance = defineEnum({
    Open: 'open',
    Restricted: 'restricted',
    backed: true,
    _cases: ['Open', 'Restricted'],
} as const);

export type ClearanceType = 'open' | 'restricted';

export type ClearanceKind = 'Open' | 'Restricted';
