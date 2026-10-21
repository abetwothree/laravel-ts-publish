<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;

/**
 * Two helpers spread under an `instanceof` proof on `$this->imageable`: one reads it, one first writes it.
 *
 * @mixin Image
 */
final class RewrittenThisHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'kept' => $this->imageable instanceof Post ? [...$this->imageableKey()] : null,
            'rewritten' => $this->imageable instanceof Post ? [...$this->swappedImageableKey()] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function imageableKey(): array
    {
        return ['key' => $this->imageable->getKey()];
    }

    /** @return array<string, mixed> */
    private function swappedImageableKey(): array
    {
        $this->imageable = new Product()->forceFill(['id' => 'p-1']);

        return ['key' => $this->imageable->getKey()];
    }
}
