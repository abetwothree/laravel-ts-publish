export const Role = {
    _cases: ['Admin', 'User', 'Guest'],
    _methods: [],
    _static: [],
    Admin: 'Admin',
    User: 'User',
    Guest: 'Guest',
} as const;

export type RoleType = 'Admin' | 'User' | 'Guest';
