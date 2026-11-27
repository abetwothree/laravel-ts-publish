<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * A test-only resource whose spread helper returns a literal holding a value the engine cannot type, beside a local
 * another helper fills with a different key it cannot type, which no write the walk saw names.
 *
 * @mixin Post
 */
final class MixedNoteJoinResource extends JsonResource
{
    /** The helper spread into a key of its own. */
    public function toArray(Request $request): array
    {
        return ['mixed' => [...$this->mixed()]];
    }

    /**
     * An `a` no rule types beside the title, or a local whose `b` no rule types.
     */
    private function mixed(): array
    {
        if ($this->id > 1) {
            return ['a' => Opaque::value($this->resource->tags), 'b' => $this->title];
        }

        $row = $this->opaqueB();

        return $row;
    }

    /**
     * The title beside a `b` no rule types.
     */
    private function opaqueB(): array
    {
        return ['a' => $this->title, 'b' => Opaque::value($this->resource->tags)];
    }
}
