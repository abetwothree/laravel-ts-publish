<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * A test-only resource whose spread helpers return, beside a typed literal, a local another helper fills with keys the
 * engine cannot type: no write the walk saw names them, and no `@return` types them. One helper is spread twice.
 *
 * @mixin Post
 */
class ReturnedKeyLeftBesideResource extends JsonResource
{
    /** Each helper spread into a key of its own, one of them twice. */
    public function toArray(Request $request): array
    {
        return [
            'relayed' => [...$this->relayed()],
            'again' => [...$this->relayed()],
            'pair' => [...$this->pair()],
        ];
    }

    /**
     * Two keys from a helper that cannot type them, or the title twice.
     */
    protected function pair(): array
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
    protected function opaquePair(): array
    {
        return ['a' => Opaque::value($this->resource->tags), 'b' => Opaque::value($this->resource->tags)];
    }

    /**
     * A `k` from a helper that cannot type it, or the title.
     */
    private function relayed(): array
    {
        if ($this->id > 1) {
            $row = $this->opaqueRow();

            return $row;
        }

        return ['k' => $this->title];
    }

    /**
     * A row whose `k` no rule types.
     */
    private function opaqueRow(): array
    {
        return ['k' => Opaque::value($this->resource->tags)];
    }
}
