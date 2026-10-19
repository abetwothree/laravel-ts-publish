<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Names a non-resource by #[Collects] beside an API resource by $collects: the attribute decides, so Laravel 13 throws.
 */
#[Collects(Dial::class)]
class DialAttributeCollection extends ResourceCollection
{
    public $collects = GaugeResource::class;
}
