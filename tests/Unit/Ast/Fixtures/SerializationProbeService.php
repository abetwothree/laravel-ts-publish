<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Exception;

/**
 * Returns values whose JSON is not what `__toString()` returns: an Exception and a `?string` jsonSerialize().
 */
final class SerializationProbeService
{
    /**
     * An exception, which json_encode() writes as `{}`.
     */
    public function failure(): Exception
    {
        return new Exception('x');
    }

    /**
     * A value whose `?string` jsonSerialize() can write null.
     */
    public function note(): StringableNullableJson
    {
        return new StringableNullableJson;
    }
}
