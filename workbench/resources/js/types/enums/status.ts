export const Status = {
    Draft: 0,
    Published: 1,
    /**
     * Get the key-value pair options for the status
     */
    keyValuePair: JSON.parse('{\u0022Draft\u0022:0,\u0022Published\u0022:1}'),
    /**
     * Get the icon name for the status
     */
    icon: {
        Draft: 'pencil',
        Published: 'check',
    },
} as const;

export type StatusType = 0 | 1;

export type StatusKind = 'Draft' | 'Published';
