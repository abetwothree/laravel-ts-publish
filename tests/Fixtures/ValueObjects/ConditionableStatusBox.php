<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Enums\Status;

/** A value object whose when() is Conditionable's, sent as its name and an enum. */
final class ConditionableStatusBox
{
    use Conditionable;

    public string $name = 'box';

    public Status $status = Status::Draft;
}
