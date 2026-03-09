import { defineEnum } from '@tolki/enum';

export const Role = defineEnum({
    Admin: 'Admin',
    User: 'User',
    Guest: 'Guest',
    _cases: ['Admin', 'User', 'Guest'],
    _methods: [],
    _static: [],
} as const);

export type RoleType = 'Admin' | 'User' | 'Guest';
