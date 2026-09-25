<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AuthoredPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;

/**
 * Keys named after AuthoredPost's model-returning accessors, each holding something other than the accessor's model.
 *
 * @mixin AuthoredPost
 */
final class AccessorNamedKeysResource extends JsonResource
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
            'author_model' => UserResource::make($this->author_model),
            'lead' => $this->lead?->email,
        ];
    }
}
