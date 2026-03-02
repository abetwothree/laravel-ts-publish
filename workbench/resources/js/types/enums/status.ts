export const Status = {
    Draft: 0,
    Published: 1,
    /** Get the icon name for the status */
    icon: {
        Draft: 'pencil',
        Published: 'check',
    },
    color: {
        Draft: 'gray',
        Published: 'green',
    },
    /** Get the key-value pair options for the status */
    valueLabelPair: [{label: 'Draft', value: 0}, {label: 'Published', value: 1}],
    names: ['Draft', 'Published'],
    values: [0, 1],
    options: {Draft: 0, Published: 1},
} as const;

export type StatusType = 0 | 1;

export type StatusKind = 'Draft' | 'Published';
