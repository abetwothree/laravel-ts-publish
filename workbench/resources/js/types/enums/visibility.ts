export const Visibility = {
    Public: 'Public',
    Private: 'Private',
    Protected: 'Protected',
    Internal: 'Internal',
    Draft: 'Draft',
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
} as const;

export type VisibilityType = 'Public' | 'Private' | 'Protected' | 'Internal' | 'Draft';
