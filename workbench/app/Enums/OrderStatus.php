<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * Int-backed enum with TsCase descriptions, instance methods, and static methods.
 * Exercises the full attribute surface area in combination.
 */
enum OrderStatus: int
{
    #[TsCase(description: 'Order has been placed but not yet processed')]
    case Pending = 0;

    #[TsCase(description: 'Order is being prepared')]
    case Processing = 1;

    #[TsCase(description: 'Order has been shipped')]
    case Shipped = 2;

    #[TsCase(description: 'Order has been delivered')]
    case Delivered = 3;

    #[TsCase(description: 'Order was cancelled')]
    case Cancelled = 4;

    #[TsCase(description: 'Order was refunded')]
    case Refunded = 5;

    #[TsCase(description: 'Order is on hold')]
    case OnHold = 6;

    #[TsEnumMethod(description: 'Human-readable label')]
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::OnHold => 'On Hold',
        };
    }

    #[TsEnumMethod(description: 'Tailwind color class for the status badge')]
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Processing => 'blue',
            self::Shipped => 'indigo',
            self::Delivered => 'green',
            self::Cancelled => 'red',
            self::Refunded => 'gray',
            self::OnHold => 'orange',
        };
    }

    #[TsEnumMethod(description: 'Whether the order can still be cancelled')]
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::OnHold]);
    }

    #[TsEnumStaticMethod(description: 'Get statuses that represent a completed order')]
    public static function completedStatuses(): array
    {
        return [
            self::Delivered->value,
            self::Cancelled->value,
            self::Refunded->value,
        ];
    }

    #[TsEnumStaticMethod(description: 'Get statuses that represent an active order')]
    public static function activeStatuses(): array
    {
        return [
            self::Pending->value,
            self::Processing->value,
            self::Shipped->value,
            self::OnHold->value,
        ];
    }

    /** Should NOT be published — no TsEnumMethod attribute */
    public function transitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Cancelled, self::OnHold]),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled, self::OnHold]),
            self::Shipped => in_array($next, [self::Delivered]),
            self::OnHold => in_array($next, [self::Processing, self::Cancelled]),
            default => false,
        };
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function defaultStatus(): self
    {
        return self::Pending;
    }
}
