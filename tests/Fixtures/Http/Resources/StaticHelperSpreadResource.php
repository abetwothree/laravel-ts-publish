<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Static helpers spread through `self::` and `static::`, and the same helpers spread through `$this`. Not final, so
 * `static::` stays as written.
 *
 * @mixin Post
 */
class StaticHelperSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...self::row($this->resource), ...static::tail($this->resource)];
    }

    /**
     * The same spreads, written through `$this`.
     *
     * @return array<string, mixed>
     */
    public function viaThis(): array
    {
        return [...$this->row($this->resource), ...$this->tail($this->resource)];
    }

    /**
     * The post's identity fields.
     *
     * @return array<string, mixed>
     */
    private static function row(Post $post): array
    {
        return ['row_id' => $post->id, 'row_title' => $post->title];
    }

    /**
     * The post's title alone.
     *
     * @return array<string, mixed>
     */
    private static function tail(Post $post): array
    {
        return ['tail_title' => $post->title];
    }
}
