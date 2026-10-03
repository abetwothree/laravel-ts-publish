import { defineEnum } from '@tolki/ts';

/**
 * Publishes the const `ClearanceType`, the name App's Clearance enum publishes its type under.
 *
 * @see Workbench\Crm\Enums\ClearanceType
 */
export const ClearanceType = defineEnum({
    Temporary: 'temporary',
    Permanent: 'permanent',
    backed: true,
    _cases: ['Temporary', 'Permanent'],
} as const);

export type ClearanceTypeType = 'temporary' | 'permanent';

export type ClearanceTypeKind = 'Temporary' | 'Permanent';
