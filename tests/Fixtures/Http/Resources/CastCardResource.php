<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource sending three author cards, two of them retyped by its class's and its toArray()'s casts.
 *
 * @mixin Post
 */
#[TsCasts(['author' => 'Record<string, unknown>'])]
class CastCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['editor' => ['type' => 'EditorCard', 'import' => '@js/types/editor']])]
    public function toArray(Request $request): array
    {
        return [
            'author' => new UserCardResource($this->author),
            'editor' => new UserCardResource($this->author),
            'writer' => new UserCardResource($this->author),
        ];
    }
}
