<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\FlagWriter;

/**
 * Literals whose discriminant a call changes through a binding the write check sees: a reference a string-keyed
 * unpack hands a parameter before its position, a static call through `$this::`, a callable pair a helper returns
 * with its keys out of order, and a local handed by reference to an object no scan names. Each literal publishes
 * flat, because PHP can send a value no arm admits (GH-174).
 *
 * @mixin Post
 */
class DispatchWritesResource extends JsonResource
{
    public bool $pinned = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $flag = (bool) $this->is_pinned;
        $writer = $this->writer();

        return [
            'named_unpack' => [
                'pinned' => $this->pinned,
                'reset' => self::flip(...['other' => 0], ...['flag' => &$this->pinned]),
                'detail' => $this->pinned ? $this->title : null,
            ],
            'static_through_this' => [
                'pinned' => $this->pinned,
                'reset' => $this::clear($this->pinned),
                'detail' => $this->pinned ? $this->title : null,
            ],
            'pair_keys_out_of_order' => [
                'is_pinned' => $this->is_pinned,
                'reset' => array_map($this->unpinner(), [0]) !== [],
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'local_by_reference' => [
                'flag' => $flag,
                'reset' => (bool) $writer->clear($flag),
                'detail' => $flag ? $this->title : null,
            ],
        ];
    }

    /**
     * Unpins the post.
     */
    public function unpin(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Clears the flag it is handed first.
     */
    private static function flip(bool &$flag, int $other): bool
    {
        $flag = false;

        return $other === 0;
    }

    /**
     * Clears the flag it is handed.
     */
    private static function clear(bool &$flag): bool
    {
        $flag = false;

        return true;
    }

    /**
     * A callable pair naming unpin(), its method written first.
     *
     * @return array<int, mixed>
     */
    private function unpinner(): array
    {
        return [1 => 'unpin', 0 => $this];
    }

    /**
     * A writer whose signature the write check does not name.
     */
    private function writer()
    {
        return new FlagWriter;
    }
}
