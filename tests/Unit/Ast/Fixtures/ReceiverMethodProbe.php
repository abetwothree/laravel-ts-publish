<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Stringable;

/** Method declarations `ReceiverMethodReturnResolver` reads, one rule per method. */
final class ReceiverMethodProbe extends JsonResource
{
    /** Public, so any receiver may call it. */
    public function label(): string
    {
        return '';
    }

    /** A class whose `__toString()` json_encode() ignores. */
    public function interval(): CarbonInterval
    {
        return CarbonInterval::day();
    }

    /**
     * The same class spelled only in a docblock.
     *
     * @return CarbonInterval
     */
    public function docblockInterval()
    {
        return CarbonInterval::day();
    }

    /** A class whose jsonSerialize() is declared as the string it stringifies to. */
    public function text(): Stringable
    {
        return new Stringable('');
    }

    /** Protected, so only `self::`/`static::`/`parent::` reach it. */
    protected static function secret(): int
    {
        return 1;
    }

    /** A request, which the receiver handler leaves to the request rule. */
    public static function request(): Request
    {
        return new Request;
    }
}
