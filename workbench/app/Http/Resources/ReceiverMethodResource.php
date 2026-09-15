<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;

/**
 * Method return types followed through every receiver kind: an enum cast, a Carbon cast, a local model,
 * a variable class for a static call, and an enum static constructor.
 *
 * @mixin Post
 */
final class ReceiverMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->author;
        $record = $this->resource;

        return [
            'priority_label' => $this->priority->label(),
            'priority_label_nullsafe' => $this->priority?->label(),
            'resource_priority_label' => $this->resource->priority->label(),
            'resource_priority_label_nullsafe' => $this->resource?->priority?->label(),
            'resource_published_date' => $this->resource->published_at->setTimezone('UTC')->toDateString(),
            'published_date' => $this->published_at->setTimezone('UTC')->toDateString(),
            'author_morph' => $author?->getMorphClass(),
            'record_class' => $record::className(),
            'from_label' => Priority::from(1)->label(),
        ];
    }
}
