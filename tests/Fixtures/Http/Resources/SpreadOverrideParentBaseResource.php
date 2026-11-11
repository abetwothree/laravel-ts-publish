<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only base whose helper overrides its own key with a literal spread.
 *
 * @mixin Post
 */
class SpreadOverrideParentBaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    protected function titled(): array
    {
        return ['a' => 1, ...['a' => $this->title]];
    }
}
