import { defineEnum } from '@tolki/enum';

/** Int-backed enum with instance methods that return different types per case. */
export const Priority = defineEnum({
    Low: 0,
    Medium: 1,
    High: 2,
    Critical: 3,
    backed: true,
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
    /** Compare with threshold */
    isAboveThreshold: {
        Low: false,
        Medium: false,
        High: true,
        Critical: true,
    },
    /** Filter by minimum */
    filterByMinimum: {"1": 1, "2": 2, "3": 3},
    _cases: ['Low', 'Medium', 'High', 'Critical'],
    _methods: ['label', 'badgeColor', 'icon', 'isAboveThreshold'],
    _static: ['filterByMinimum'],
} as const);

export type PriorityType = 0 | 1 | 2 | 3;

export type PriorityKind = 'Low' | 'Medium' | 'High' | 'Critical';
