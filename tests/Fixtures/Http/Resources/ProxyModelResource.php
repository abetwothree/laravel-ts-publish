<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/** A base resource that redeclares `$resource` as any model, leaving each subclass to name its own. */
class ProxyModelResource extends JsonResource
{
    /** @var Model */
    public $resource;
}
