<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\HeldStatsVarParentResource;
use Workbench\App\Models\Post;

/** A body-less subclass that redeclares `$resource` as a post over its parent's value-object `@var`. */
final class PostVarHeldStatsChildResource extends HeldStatsVarParentResource
{
    /** @var Post */
    public $resource;
}
