<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of AppendedTagsBaseResource's `toArray()` that preserves keys and prunes an array. */
final class KeptTagsResource extends AppendedTagsBaseResource
{
    public $preserveKeys = true;

    /** Drop the first pruned entry, then append another. */
    public function prune(): static
    {
        unset($this->pruned[0]);
        $this->pruned[] = 'q';

        return $this;
    }
}
