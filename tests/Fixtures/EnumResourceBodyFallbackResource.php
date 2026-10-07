<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose one key reads a vague `: array` helper that wraps an enum held in a local, beside a title.
 *
 * @mixin Post
 */
class EnumResourceBodyFallbackResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['summary' => $this->summary()];
    }

    /**
     * The post's status as an enum resource, beside its title.
     */
    public function summary(): array
    {
        $s = $this->status;

        return ['s' => EnumResource::make($s), 'title' => (string) $this->title];
    }
}
