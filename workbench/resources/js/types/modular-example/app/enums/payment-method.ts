import { defineEnum } from '@tolki/enum';

export const PaymentMethod = defineEnum({
    CreditCard: 'credit_card',
    DebitCard: 'debit_card',
    PayPal: 'paypal',
    BankTransfer: 'bank_transfer',
    CashOnDelivery: 'cash_on_delivery',
    Crypto: 'crypto',
    ApplePay: 'apple_pay',
    GooglePay: 'google_pay',
    _cases: ['CreditCard', 'DebitCard', 'PayPal', 'BankTransfer', 'CashOnDelivery', 'Crypto', 'ApplePay', 'GooglePay'],
} as const);

export type PaymentMethodType = 'credit_card' | 'debit_card' | 'paypal' | 'bank_transfer' | 'cash_on_delivery' | 'crypto' | 'apple_pay' | 'google_pay';

export type PaymentMethodKind = 'CreditCard' | 'DebitCard' | 'PayPal' | 'BankTransfer' | 'CashOnDelivery' | 'Crypto' | 'ApplePay' | 'GooglePay';
