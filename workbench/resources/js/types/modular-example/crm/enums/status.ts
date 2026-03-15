import { defineEnum } from '@tolki/enum';

export const Status = defineEnum({
    Lead: 'lead',
    Prospect: 'prospect',
    Active: 'active',
    Churned: 'churned',
    _cases: ['Lead', 'Prospect', 'Active', 'Churned'],
} as const);

export type StatusType = 'lead' | 'prospect' | 'active' | 'churned';

export type StatusKind = 'Lead' | 'Prospect' | 'Active' | 'Churned';
