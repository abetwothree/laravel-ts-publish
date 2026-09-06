import type { RoleType } from '../enums';

export const UserModelMetadata = {
    morphClass: 'Workbench\\App\\Models\\User',
    enabled: true,
    limits: {minimum: 1, maximum: null},
    role: 'Admin',
} as const satisfies {
    morphClass: string;
    enabled: boolean;
    limits: { minimum: number; maximum: null };
    role: RoleType;
};
