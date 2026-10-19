<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Wrapped\ForwardedMethodWrapped;
use Illuminate\Http\Resources\Json\JsonResource;

/** A resource wrapping a plain class while its subject model is a post. */
class ForwardedMethodResource extends JsonResource
{
    /** @var ForwardedMethodWrapped */
    public $resource;
}
