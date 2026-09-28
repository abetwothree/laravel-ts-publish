<?php

declare(strict_types=1);

namespace Workbench\App\Services;

/**
 * Vague `: array` signatures whose literal bodies are the only place the shape is written.
 *
 * Deliberately carries no `@return` docblock: a shape there would be read before the body ever is,
 * so these two methods would stop exercising the body fallback at all.
 */
final class PriceQuoteService
{
    public const int TIER_BASIC = 1;

    public const int TIER_PRO = 2;

    /** A vague signature over a literal body: the body is the only place the shape is written. */
    public function quote(): array
    {
        return ['unit' => '1.00', 'minimum' => 10, 'discounted' => ['unit' => '0.90']];
    }

    /** Keys are class constants, so the JSON is an object keyed "1" and "2", not a list. */
    public static function tierLabels(): array
    {
        return [self::TIER_BASIC => 'Basic', self::TIER_PRO => 'Pro'];
    }
}
