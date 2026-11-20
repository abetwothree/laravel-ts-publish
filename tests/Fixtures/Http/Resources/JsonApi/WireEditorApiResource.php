<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose class cast keeps the text of an attribute no status picks, importing its class from
 * elsewhere, beside attributes the status picks. Laravel 12.45 and later.
 *
 * @mixin Post
 */
#[TsCasts(['editor' => ['type' => 'UserResource', 'import' => '@js/types/wire']])]
class WireEditorApiResource extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'editor' => new UserResource($this->author),
            'status' => $this->status,
            'published_title' => $this->status === Status::Published ? $this->title : null,
        ];
    }
}
