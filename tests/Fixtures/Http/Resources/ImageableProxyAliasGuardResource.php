<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block replaces it through a local holding `$this->resource`.
 *
 * @mixin Image
 */
final class ImageableProxyAliasGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $model = $this->resource;

        if ($this->imageable instanceof Post) {
            $model->setRelation('imageable', $model->reviewable);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
