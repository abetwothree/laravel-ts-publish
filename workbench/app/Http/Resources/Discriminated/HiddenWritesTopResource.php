<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;

/**
 * A top-level literal whose discriminants a call inside an arm writes between two of their reads: `changed` clears
 * the pin before `detail` reads it, and the arm `level` takes for `null` sets the priority before `check`, which
 * throws for `null`, reads it. PHP can send a value no arm admits, so the literal publishes flat (GH-174).
 *
 * @mixin Post
 */
class HiddenWritesTopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            'changed' => $this->is_pinned ? $this->clearPinAndLabel() : null,
            'detail' => $this->is_pinned ? $this->title : null,
            'priority' => $this->priority,
            'level' => match ($this->priority) {
                Priority::Low => 'low',
                null => $this->restorePriority(),
                default => null,
            },
            'check' => match ($this->priority) {
                null => throw new LogicException('unset'),
                default => 'ok',
            },
        ];
    }

    /**
     * Clears the pin on the model and returns a label.
     */
    private function clearPinAndLabel(): string
    {
        $this->resource->is_pinned = false;

        return 'label';
    }

    /**
     * Sets the model's missing priority.
     */
    private function restorePriority(): bool
    {
        $this->resource->priority = Priority::Low;

        return true;
    }
}
