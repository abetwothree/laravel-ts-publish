<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource overriding `when()` with one that only picks a value, so the framework's `unless()`, which calls
 * it, still only reads.
 *
 * @mixin Post
 */
final class ReadingWhenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['detail' => $this->when($this->is_pinned, $this->title)];
    }

    /**
     * Picks the value or the default, calling neither.
     */
    protected function when($condition, $value, $default = null): mixed
    {
        return $condition ? $value : $default;
    }
}
