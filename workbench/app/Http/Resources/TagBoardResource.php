<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * Fixture: a runtime class of BaseTagBoardResource's `toArray()` that appends to an object, appends beside a key
 * write, and keys an object by the tag passed.
 */
final class TagBoardResource extends BaseTagBoardResource
{
    /** Append the tag passed, and key it in. */
    public function tag(string $tag): static
    {
        $this->labels[] = $tag;
        $this->both[] = $tag;
        $this->both['k'] = 1;
        $this->dynamic[$tag] = 1;

        return $this;
    }
}
