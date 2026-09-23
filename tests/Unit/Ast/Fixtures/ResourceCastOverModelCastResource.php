<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Product;

/**
 * Its own cast on `metadata` replaces the model's, whose type is imported.
 *
 * @mixin Product
 */
#[TsCasts(['metadata' => 'string'])]
final class ResourceCastOverModelCastResource extends JsonResource
{
    /** Publishes the model-cast key. */
    public function toArray(Request $request): array
    {
        return ['metadata' => $this->metadata];
    }
}
