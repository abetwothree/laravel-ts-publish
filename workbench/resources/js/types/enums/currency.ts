import { defineEnum } from '@tolki/enum';

export const Currency = defineEnum({
    Usd: 'USD',
    Eur: 'EUR',
    Gbp: 'GBP',
    Jpy: 'JPY',
    Cad: 'CAD',
    /** Get all currency symbols as a map */
    symbols: {USD: '$', EUR: '€', GBP: '£', JPY: '¥', CAD: 'C$'},
    /** Get the default currency code */
    default: 'USD',
    /** Get detailed info for all currencies */
    details: [{code: 'USD', symbol: '$', decimals: 2}, {code: 'EUR', symbol: '€', decimals: 2}, {code: 'GBP', symbol: '£', decimals: 2}, {code: 'JPY', symbol: '¥', decimals: 0}, {code: 'CAD', symbol: 'C$', decimals: 2}],
    _cases: ['Usd', 'Eur', 'Gbp', 'Jpy', 'Cad'],
    _methods: [],
    _static: ['symbols', 'default', 'details'],
} as const);

export type CurrencyType = 'USD' | 'EUR' | 'GBP' | 'JPY' | 'CAD';

export type CurrencyKind = 'Usd' | 'Eur' | 'Gbp' | 'Jpy' | 'Cad';
