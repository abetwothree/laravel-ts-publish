import { defineEnum } from '@tolki/ts';

/**
 * @see Workbench\App\Enums\Role
 */
export const Role = defineEnum({
    Admin: 'Admin',
    User: 'User',
    Guest: 'Guest',
    backed: false,
    _cases: ['Admin', 'User', 'Guest'],
} as const);

export type RoleType = 'Admin' | 'User' | 'Guest';
