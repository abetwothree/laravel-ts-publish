<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parent resource whose generic declares its subclasses hold a value object, not a model.
 *
 * @extends JsonResource<HeldStats>
 */
abstract class HeldStatsParentResource extends JsonResource {}
