<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SchedulePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource spreading a related model's toArray(), which keeps a JsonSerializable cast value as an object
 * and writes an Arrayable one as its toArray() array.
 *
 * @mixin SchedulePost
 */
final class SchedulePostSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->sibling->toArray(), 'spread' => true];
    }
}
