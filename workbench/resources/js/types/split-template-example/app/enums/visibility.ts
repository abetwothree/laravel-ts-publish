import { defineEnum } from '@tolki/ts';

/**
 * Pure unit enum (no backing type) — tests that the publisher handles unit enums where case names become the values in TypeScript.
 *
 * @see Workbench\App\Enums\Visibility
 */
export const Visibility = defineEnum({
    Public: 'Public',
    Private: 'Private',
    Protected: 'Protected',
    Internal: 'Internal',
    Draft: 'Draft',
    backed: false,
    /** Whether the item is publicly accessible */
    isPublic: {
        Public: true,
        Private: false,
        Protected: false,
        Internal: false,
        Draft: false,
    },
    /** Description of the visibility level */
    description: {
        Public: 'Visible to everyone',
        Private: 'Only visible to the owner',
        Protected: 'Visible to team members',
        Internal: 'Visible to organization members',
        Draft: 'Not visible to anyone except the author',
    },
    _cases: ['Public', 'Private', 'Protected', 'Internal', 'Draft'],
    _methods: ['isPublic', 'description'],
} as const);

export type VisibilityType = 'Public' | 'Private' | 'Protected' | 'Internal' | 'Draft';
