<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Wrapped;

/** A class a resource wraps, declaring one helper the model also declares and one only it has. */
class ForwardedMethodWrapped
{
    public static function onBoth(): int
    {
        return 1;
    }

    public static function onWrapped(): bool
    {
        return true;
    }

    protected static function hiddenOnWrapped(): int
    {
        return 1;
    }
}
