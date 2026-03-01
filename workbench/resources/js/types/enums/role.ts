export const Role = {
    Admin: 'Admin',
    User: 'User',
    Guest: 'Guest',
} as const;

export type RoleType = 'Admin' | 'User' | 'Guest';
