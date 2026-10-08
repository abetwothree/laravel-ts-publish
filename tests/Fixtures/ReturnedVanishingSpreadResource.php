<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable spreads itself to re-set a key to a value that can vanish.
 *
 * @mixin Tag
 */
class ReturnedVanishingSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = ['id' => $this->id, 'a' => 1];
        $d = [...$d, 'a' => $this->whenLoaded('posts')];

        return $d;
    }
}
