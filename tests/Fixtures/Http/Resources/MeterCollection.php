<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Names a non-resource by $collects beside a convention candidate that is one: $collects decides, so Laravel throws.
 */
class MeterCollection extends ResourceCollection
{
    public $collects = Dial::class;
}
