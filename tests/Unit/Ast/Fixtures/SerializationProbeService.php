<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Carbon\CarbonInterface;
use DateTime;
use Exception;
use Illuminate\Support\Carbon;

/**
 * Returns and holds values whose JSON is not what `__toString()` returns: an Exception, a plain DateTime, a `?string`
 * jsonSerialize(), and the CarbonInterface now() declares.
 */
final class SerializationProbeService
{
    public ?Exception $lastError = null;

    public DateTime $openedAt;

    /**
     * Open with the current date.
     */
    public function __construct()
    {
        $this->openedAt = new DateTime;
    }

    /**
     * An exception, which json_encode() writes as `{}`.
     */
    public function failure(): Exception
    {
        return new Exception('x');
    }

    /**
     * Exceptions, a list of `{}`.
     *
     * @return list<Exception>
     */
    public function failures(): array
    {
        return [new Exception('x')];
    }

    /**
     * A plain DateTime, which json_encode() writes as its date object.
     */
    public function openedOn(): DateTime
    {
        return new DateTime;
    }

    /**
     * A value whose `?string` jsonSerialize() can write null.
     */
    public function note(): StringableNullableJson
    {
        return new StringableNullableJson;
    }

    /**
     * What now() returns.
     */
    public function now(): CarbonInterface
    {
        return Carbon::now();
    }
}
