<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AuthoredPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keys named after AuthoredPost's model-returning accessors, each holding the accessor's model.
 *
 * @mixin AuthoredPost
 */
final class AccessorNamedModelsResource extends JsonResource
{
    /**
     * The keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_model' => $this->author_model,
            'lead' => $this->lead,
        ];
    }
}
