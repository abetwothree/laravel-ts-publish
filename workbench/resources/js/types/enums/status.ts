export const Status = {
    Draft: 0,
    Published: 1,
    /**
     * Get the key-value pair options for the status
     */
    keyValuePair: {Draft: 0, Published: 1},
    values: [0, 1],
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
