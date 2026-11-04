<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** A parent resource whose `$resource` `@var` names the app's `Post`, which only this file imports. */
class PostWrappingResource extends JsonResource
{
    /** @var Post */
    public $resource;
}
