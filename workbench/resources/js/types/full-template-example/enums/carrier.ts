import { defineEnum } from '@tolki/enum';

export const Carrier = defineEnum({
    Ups: 'ups',
    FedEx: 'fedex',
    Usps: 'usps',
    Dhl: 'dhl',
    _cases: ['Ups', 'FedEx', 'Usps', 'Dhl'],
} as const);

export type CarrierType = 'ups' | 'fedex' | 'usps' | 'dhl';

export type CarrierKind = 'Ups' | 'FedEx' | 'Usps' | 'Dhl';
