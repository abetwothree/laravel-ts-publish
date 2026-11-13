<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\HeldStatsMembersParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FlaggedStats;

/**
 * A body-less subclass whose generic names another value object over its parent's.
 *
 * @extends HeldStatsMembersParentResource<FlaggedStats>
 */
final class FlaggedStatsExtendsChildResource extends HeldStatsMembersParentResource {}
