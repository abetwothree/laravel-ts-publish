<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\AttachmentResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose class cast keeps the text inference gives an attribute the priority picks, but imports its
 * class from elsewhere: the attributes publish flat, with the cast's class (GH-157).
 *
 * @mixin Post
 */
#[TsCasts(['attachment' => ['type' => 'AttachmentResource | null', 'import' => '@js/types/wire']])]
class WireAttachmentPostResource extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'priority' => $this->priority,
            'attachment' => $this->priority === Priority::Critical ? new AttachmentResource($this->attachment) : null,
        ];
    }
}
