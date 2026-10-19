<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose spread helper casts the numeric key `42`, which PHP stores as an int.
 *
 * @mixin Post
 */
class NumericCastSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...$this->flags()];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['42' => 'boolean'])]
    protected function flags(): array
    {
        return ['title' => $this->title];
    }
}
