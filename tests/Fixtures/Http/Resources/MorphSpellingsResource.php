<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\RosterSlot;

/**
 * Every spelling of a method call on a `MorphTo<Model, $this>` relation, none of which names its targets, next to the
 * narrowed relation that does. The first group is an honest unknown.
 *
 * @extends JsonResource<RosterSlot>
 */
final class MorphSpellingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'proxy_title' => $this->assignable->displayTitle(),
            'proxy_title_nullsafe' => $this->assignable?->displayTitle(),
            'resource_title' => $this->resource->assignable->displayTitle(),
            'resource_title_nullsafe' => $this->resource->assignable?->displayTitle(),
            'static_label' => $this->assignable::singularLabel(),
            'assignee_title' => $this->assignee->displayTitle(),
            'assignee_title_nullsafe' => $this->assignee?->displayTitle(),
        ];
    }
}
