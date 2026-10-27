<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * Spreads and nests the helper it inherits, whose `self::label()` stays InheritedHelperBase's though this class
 * redeclares label().
 */
final class InheritedHelperPayload extends InheritedHelperBase
{
    public static function label(): string
    {
        return 'x';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...$this->helper(), 'v' => $this->helper()];
    }
}
