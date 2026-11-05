<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\HeldStatsVarMembersParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FlaggedStats;

/** A body-less subclass that redeclares `$resource` as another value object over its parent's `@var`. */
final class FlaggedStatsVarChildResource extends HeldStatsVarMembersParentResource
{
    /** @var FlaggedStats */
    public $resource;
}
