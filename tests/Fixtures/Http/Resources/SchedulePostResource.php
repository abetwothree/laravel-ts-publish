<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SchedulePost;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource sending its model's own toArray(), which keeps a JsonSerializable cast value as an object and
 * writes an Arrayable one as its toArray() array.
 *
 * @mixin SchedulePost
 */
final class SchedulePostResource extends JsonResource {}
