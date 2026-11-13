<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/** A parent resource that redeclares `$resource` as any model, for subclasses to name theirs. */
abstract class ModelWrappingResource extends JsonResource
{
    /** @var Model */
    public $resource;
}
