<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A resource with no model whose `if` tests `$this->comments` for a collection, then reads `random()` and `count()`.
 */
final class CommentsGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->comments instanceof Collection) {
            return ['random' => $this->comments->random(), 'count' => $this->comments->count()];
        }

        return ['random' => null, 'count' => 0];
    }
}
