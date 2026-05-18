import { defineEnum } from '@tolki/ts';

/**
 * @see Workbench\Crm\Enums\Status
 */
export const Status = defineEnum({
    Lead: 'lead',
    Prospect: 'prospect',
    Active: 'active',
    Churned: 'churned',
    backed: true,
    _cases: ['Lead', 'Prospect', 'Active', 'Churned'],
} as const);

export type StatusType = 'lead' | 'prospect' | 'active' | 'churned';

export type StatusKind = 'Lead' | 'Prospect' | 'Active' | 'Churned';
