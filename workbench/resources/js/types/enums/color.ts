export const Color = {
    /** Primary red color */
    Red: 'red',
    /** Primary green color */
    Green: 'green',
    /** Primary blue color */
    Blue: 'blue',
    /** Warning yellow */
    Yellow: 'yellow',
    Slate: 'slate',
    Purple: 'purple',
    /** Get the hex code for the color */
    hex: {
        Red: '#EF4444',
        Green: '#22C55E',
        Blue: '#3B82F6',
        Amber: '#F59E0B',
        Gray: '#64748B',
        Purple: '#A855F7',
    },
    /** Get the RGB tuple */
    rgb: {
        Red: [239, 68, 68],
        Green: [34, 197, 94],
        Blue: [59, 130, 246],
        Amber: [245, 158, 11],
        Gray: [100, 116, 139],
        Purple: [168, 85, 247],
    },
} as const;

export type ColorType = 'red' | 'green' | 'blue' | 'yellow' | 'slate' | 'purple';

export type ColorKind = 'Red' | 'Green' | 'Blue' | 'Yellow' | 'Slate' | 'Purple';
