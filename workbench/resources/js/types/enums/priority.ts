export const Priority = {
    Low: 0,
    Medium: 1,
    High: 2,
    Critical: 3,
    /** Human-readable label */
    label: {
        Low: 'Low Priority',
        Medium: 'Medium Priority',
        High: 'High Priority',
        Critical: 'Critical Priority',
    },
    /** Tailwind badge color class */
    badgeColor: {
        Low: 'bg-gray-100 text-gray-800',
        Medium: 'bg-blue-100 text-blue-800',
        High: 'bg-orange-100 text-orange-800',
        Critical: 'bg-red-100 text-red-800',
    },
    /** Icon name for the priority level */
    icon: {
        Low: 'arrow-down',
        Medium: 'minus',
        High: 'arrow-up',
        Critical: 'exclamation-triangle',
    },
} as const;

export type PriorityType = 0 | 1 | 2 | 3;

export type PriorityKind = 'Low' | 'Medium' | 'High' | 'Critical';
