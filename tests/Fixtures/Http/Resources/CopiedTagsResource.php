<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource, which re-indexes, whose untyped arrays copy one a whole write may reorder, then drop a key. */
final class CopiedTagsResource extends JsonResource
{
    protected $source = [0 => 'x', 1 => 5, 'n' => 1];

    protected $copied = [0 => 'x', 1 => 5, 'n' => 1];

    protected $blank = [];

    protected $fetched = [0 => 'x', 1 => 5, 'n' => 1];

    /** Replace the source with one holding its numbers in the other order. */
    public function flip(): static
    {
        $this->source = [1 => 5, 0 => 'x', 'n' => 2];

        return $this;
    }

    /** Rewrite the source's named key. */
    public function recount(): static
    {
        $this->source['n'] = 3;

        return $this;
    }

    /** Copy the source into each other array, directly or through a helper, and drop its named key. */
    public function copy(): static
    {
        $this->copied = $this->source;
        unset($this->copied['n']);
        $this->blank = $this->source;
        unset($this->blank['n']);
        $this->fetched = $this->current();
        unset($this->fetched['n']);

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'source' => $this->source,
            'copied' => $this->copied,
            'blank' => $this->blank,
            'fetched' => $this->fetched,
        ];
    }

    /** The source as it is now, read through a helper rather than the property. */
    private function current(): array
    {
        return $this->source;
    }
}
