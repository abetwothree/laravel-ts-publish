<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose generic declares it holds a value object or a post.
 *
 * @extends JsonResource<HeldStats|Post>
 */
final class HeldStatsOrPostExtendsResource extends JsonResource {}
