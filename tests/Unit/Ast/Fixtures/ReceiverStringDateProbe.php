<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/**
 * The honest twin of ReceiverVarProbe::$plainDate: the same property name holding a real string, so a
 * receiver union of the two has one arm json_encode() writes as an object and one it writes as a string.
 */
final class ReceiverStringDateProbe
{
    public string $plainDate = '';
}
