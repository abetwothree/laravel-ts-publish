<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * A test-only resource whose warnings must name the value each leaves out: a local's key that defaults to itself, which
 * the walk also reads under an alias of its own, and two keys of one return beside a `@return` that types only one.
 *
 * @mixin Post
 */
final class LeftOutNamesResource extends JsonResource
{
    /** @return array{pair: array{a: string}} */
    public function toArray(Request $request): array
    {
        return ['own_default' => $this->ownDefault(), 'pair' => [...$this->pair()]];
    }

    /**
     * A key read back as its own default.
     */
    private function ownDefault(): array
    {
        $o = ['k' => null];
        $o['k'] = $o['k'] ?? $this->title;

        return $o;
    }

    /**
     * Two keys from a helper that cannot type them, or the title twice.
     */
    private function pair(): array
    {
        if ($this->id > 1) {
            $row = $this->opaquePair();

            return $row;
        }

        return ['a' => $this->title, 'b' => $this->title];
    }

    /**
     * A row whose `a` and `b` no rule types.
     */
    private function opaquePair(): array
    {
        return ['a' => Opaque::value($this->resource->tags), 'b' => Opaque::value($this->resource->tags)];
    }
}
