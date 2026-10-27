<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of AppendedTagsBaseResource's `toArray()` that appends to, keys and prunes its arrays. */
final class AppendedTagsResource extends AppendedTagsBaseResource
{
    /** Append the tag passed, and key one more count in, under a literal key or the tag itself. */
    public function tag(string $tag): static
    {
        $this->tags[] = $tag;
        $this->empty[] = $tag;
        $this->keyed['b'] = 2;
        $this->counts['a'] = 'many';
        $this->mixed[] = $tag;
        $this->labels[] = $tag;
        $this->both[] = $tag;
        $this->both['k'] = 1;
        $this->dynamic[$tag] = 1;

        return $this;
    }

    /** Replace the mixed list with numbers. */
    public function renumber(): static
    {
        $this->mixed = [1, 2];

        return $this;
    }

    /** Drop the first pruned entry, then append another. */
    public function prune(): static
    {
        unset($this->pruned[0]);
        $this->pruned[] = 'q';

        return $this;
    }
}
