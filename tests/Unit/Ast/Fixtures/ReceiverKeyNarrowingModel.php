<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** An abstract model narrowing getKey() natively, which PHP's covariant returns hold every subclass to. */
abstract class ReceiverKeyNarrowingModel extends Model
{
    public function getKey(): int
    {
        return (int) parent::getKey();
    }
}
