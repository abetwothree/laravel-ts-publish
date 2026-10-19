<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Over a parent collecting by its name, its own `$collects` names `Dial`, which is no API resource. */
class F38DialLeafCollection extends StubGizmoCollection
{
    public $collects = Dial::class;
}
