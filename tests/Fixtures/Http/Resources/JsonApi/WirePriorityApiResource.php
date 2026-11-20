<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose class cast keeps the text inference gives its discriminant attribute, but imports it from
 * elsewhere. Kept out of the golden tree: the globals file qualifies that name to the published enum. Laravel 12.45 and
 * later.
 *
 * @mixin Post
 */
#[TsCasts(['priority' => ['type' => 'PriorityType | null', 'import' => '@js/types/wire']])]
class WirePriorityApiResource extends JsonApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'priority' => $this->priority,
            'escalated_title' => $this->priority === Priority::Critical ? $this->title : null,
        ];
    }
}
