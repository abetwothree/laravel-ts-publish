<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;

/**
 * Keys whose arms send one enum wrapped in an enum resource for one value and read directly for another (GH-157):
 * each value keeps its own arm, at the top level, in a nested literal and with the arms written in another order.
 *
 * @mixin Post
 */
class WrapOrReadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'priority' => $this->priority,
            'status' => match ($this->priority) {
                Priority::Low => EnumResource::make($this->status),
                Priority::Medium => $this->status,
                default => null,
            },
            'tally' => match ($this->priority) {
                Priority::Low, Priority::Medium => 1,
                default => 'none',
            },
            'is_pinned' => $this->is_pinned,
            'stage' => $this->is_pinned ? EnumResource::make($this->status) : $this->status,
            'reordered' => [
                'priority' => $this->priority,
                'status' => match ($this->priority) {
                    Priority::Medium => $this->status,
                    Priority::Low => EnumResource::make($this->status),
                    default => null,
                },
                'tally' => match ($this->priority) {
                    Priority::Low, Priority::Medium => 1,
                    default => 'none',
                },
            ],
        ];
    }
}
