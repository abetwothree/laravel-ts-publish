<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block swaps it through a local `$this->resource ?? null` set.
 *
 * @mixin Image
 */
final class ImageableCoalesceAliasGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $model = $this->resource ?? null;

        if ($this->imageable instanceof Post) {
            $model->setRelation('imageable', $model->reviewable);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
