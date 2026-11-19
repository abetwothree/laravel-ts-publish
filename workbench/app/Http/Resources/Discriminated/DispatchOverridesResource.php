<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * Literals whose discriminant code changes through what a call dispatches to: parent::unless(), which calls the
 * when() this resource overrides to unpin the post, and the `tick` get hook TickingResource declares, whose
 * `self::clearTick()` runs the parent's clearTick(), not the one here. PHP can send a value no arm admits, so each
 * literal should publish flat (GH-174).
 *
 * @mixin Post
 */
class DispatchOverridesResource extends TickingResource
{
    public bool $tick = true {
        set => $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'parent_unless' => [
                'is_pinned' => $this->is_pinned,
                'note' => (string) parent::unless(false, 'note'),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'parent_hook_self' => [
                'is_pinned' => $this->is_pinned,
                'tick' => $this->tick,
                'detail' => $this->is_pinned ? $this->title : null,
            ],
        ];
    }

    /**
     * Reads nothing, where the parent's clearTick() unpins.
     */
    public function clearTick(): void {}

    /**
     * Unpins the post, then decides as the framework's when() does.
     */
    protected function when($condition, $value, $default = null): mixed
    {
        $this->resource->is_pinned = false;

        return parent::when(...func_get_args());
    }
}
