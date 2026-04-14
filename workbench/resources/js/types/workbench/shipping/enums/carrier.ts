import { defineEnum } from '@tolki/ts';

/**
 * @see Workbench\Shipping\Enums\Carrier
 */
export const Carrier = defineEnum({
    Ups: 'ups',
    FedEx: 'fedex',
    Usps: 'usps',
    Dhl: 'dhl',
    backed: true,
    _cases: ['Ups', 'FedEx', 'Usps', 'Dhl'],
} as const);

export type CarrierType = 'ups' | 'fedex' | 'usps' | 'dhl';

export type CarrierKind = 'Ups' | 'FedEx' | 'Usps' | 'Dhl';
