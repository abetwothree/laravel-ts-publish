import { defineEnum } from '@tolki/enum';

export const Visibility = defineEnum({
    Public: 'Public',
    Private: 'Private',
    Protected: 'Protected',
    Internal: 'Internal',
    Draft: 'Draft',
    /** Whether the item is publicly accessible */
    is_public: {
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
    _methods: ['is_public', 'description'],
    _static: [],
} as const);

export type VisibilityType = 'Public' | 'Private' | 'Protected' | 'Internal' | 'Draft';
