<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

/**
 * String-backed enum with no attributes at all — tests the simplest backed enum path.
 */
enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';
    case CashOnDelivery = 'cash_on_delivery';
    case Crypto = 'crypto';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';

    /** Should NOT be published — no TsEnumMethod attribute */
    public function isDigitalWallet(): bool
    {
        return in_array($this, [self::ApplePay, self::GooglePay, self::PayPal]);
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function onlineOnly(): array
    {
        return [self::PayPal, self::Crypto, self::ApplePay, self::GooglePay];
    }
}
