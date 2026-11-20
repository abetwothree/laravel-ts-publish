<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PinSettings;

/**
 * Literals whose read an item between its keys writes where the literal cannot see it: a helper's body, the model
 * through `$this->resource`, and a `->` write of a `?->` read. Each stays flat, while a helper writing nothing keeps
 * its literal split (GH-157).
 *
 * @mixin Post
 */
class UnseenWritesResource extends JsonResource
{
    public ?PinSettings $settings = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'by_helper' => [
                'is_pinned' => $this->is_pinned,
                'reset' => $this->clearPin(),
                'title' => $this->is_pinned ? $this->title : null,
            ],
            'by_proxy' => [
                'is_pinned' => $this->is_pinned,
                'reset' => (bool) ($this->resource->is_pinned = false),
                'title' => $this->is_pinned ? $this->title : null,
            ],
            'by_arrow' => [
                'enabled' => $this->settings?->enabled,
                'off' => (bool) ($this->settings->enabled = false),
                'title' => $this->settings?->enabled ? $this->title : null,
            ],
            'by_reader' => [
                'is_pinned' => $this->is_pinned,
                'label' => $this->label(),
                'title' => $this->is_pinned ? $this->title : null,
            ],
        ];
    }

    /**
     * Clears the pin on the model.
     */
    private function clearPin(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * The title as a label, writing nothing.
     */
    private function label(): string
    {
        return (string) $this->title;
    }
}
