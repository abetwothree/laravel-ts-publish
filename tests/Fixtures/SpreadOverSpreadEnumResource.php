<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that spreads two helpers setting one key: the first wraps an enum, the second, which wins, does
 * not.
 *
 * @mixin Post
 */
class SpreadOverSpreadEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->wrapped(), ...$this->plain()];
    }

    /** @return array<string, mixed> */
    protected function wrapped(): array
    {
        return ['k' => EnumResource::make($this->status)];
    }

    /** @return array<string, mixed> */
    protected function plain(): array
    {
        return ['k' => $this->title];
    }
}
