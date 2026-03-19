import { defineEnum } from '@tolki/enum';

export const Role = defineEnum({
    Admin: 'Admin',
    User: 'User',
    Guest: 'Guest',
    backed: false,
    _cases: ['Admin', 'User', 'Guest'],
} as const);

export type RoleType = 'Admin' | 'User' | 'Guest';
