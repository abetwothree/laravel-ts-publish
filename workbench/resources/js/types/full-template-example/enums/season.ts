import { defineEnum } from '@tolki/enum';

/** The four seasons of the year */
export const Season = defineEnum({
    Spring: 'spring',
    Summer: 'summer',
    Autumn: 'autumn',
    Winter: 'winter',
    backed: true,
    /** Average temperature in Celsius */
    avgTemp: {
        Spring: 15,
        Summer: 30,
        Autumn: 12,
        Winter: -5,
    },
    /** Only works for warm seasons */
    warmGreeting: {
        Spring: 'Enjoy the blooms!',
        Summer: 'Stay cool!',
        Autumn: 'Enjoy the leaves!',
        Winter: null,
    },
    /** This always throws */
    broken: null,
    _cases: ['Spring', 'Summer', 'Autumn', 'Winter'],
    _methods: ['avgTemp', 'warmGreeting'],
    _static: ['broken'],
} as const);

export type SeasonType = 'spring' | 'summer' | 'autumn' | 'winter';

export type SeasonKind = 'Spring' | 'Summer' | 'Autumn' | 'Winter';
