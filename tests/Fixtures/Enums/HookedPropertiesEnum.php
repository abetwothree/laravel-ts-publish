<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HookedPropertiesValue;
use ReflectionClass;
use Workbench\App\ValueObjects\RateCard;

/** An enum whose methods return a hooked object and a lazy ghost, which json_encode() reads through. */
enum HookedPropertiesEnum: string
{
    case Dock = 'dock';

    /** A value whose get hooks rewrite one property and compute another. */
    #[TsEnumMethod]
    public function label(): HookedPropertiesValue
    {
        return new HookedPropertiesValue($this->value);
    }

    /** A rate card that is only constructed when json_encode() first reads it. */
    #[TsEnumMethod]
    public function rate(): RateCard
    {
        return (new ReflectionClass(RateCard::class))->newLazyGhost(fn (RateCard $card) => $card->__construct(9));
    }
}
