<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a relation `whenLoaded()` leaves out when not loaded.
 *
 * @mixin Tag
 */
class ReturnedCoalesceWhenLoadedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'posts' => $this->whenLoaded('posts')];
        $data['posts'] ??= 'none';

        return $data;
    }
}
