import { defineEnum } from '@tolki/ts';

/**
 * Freight classes whose methods return objects, each published as json_encode() writes it.
 *
 * @see Workbench\App\Enums\FreightClass
 */
export const FreightClass = defineEnum({
    Standard: 'standard',
    Express: 'express',
    backed: true,
    /** Rate card for the class */
    rateCard: {
        Standard: {amount: 100},
        Express: {amount: 250},
    },
    /** Daily pickup cutoff */
    cutoff: {
        Standard: '2026-01-01T17:00:00.000000Z',
        Express: '2026-01-01T20:00:00.000000Z',
    },
    tracking: {
        Standard: {code: 'STANDARD', carrier: 'ups'},
        Express: {code: 'EXPRESS', carrier: 'ups'},
    },
    zones: {
        Standard: ['north', 'south'],
        Express: ['north'],
    },
    firstPickup: {
        Standard: {date: '2026-01-01 09:00:00.000000', timezone_type: 3, timezone: 'UTC'},
        Express: {date: '2026-01-01 09:00:00.000000', timezone_type: 3, timezone: 'UTC'},
    },
    manifest: {
        Standard: {},
        Express: {},
    },
    defaultRate: {amount: 50},
    _cases: ['Standard', 'Express'],
    _methods: ['rateCard', 'cutoff', 'tracking', 'zones', 'firstPickup', 'manifest'],
    _static: ['defaultRate'],
} as const);

export type FreightClassType = 'standard' | 'express';

export type FreightClassKind = 'Standard' | 'Express';
