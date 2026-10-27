<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * Fixture: a runtime class of BaseNoticeResource's `toArray()` that writes the untyped `$tier` it inherits through a
 * typed setter.
 */
final class TieredNoticeResource extends BaseNoticeResource
{
    /** Move the tier to the one passed. */
    public function withTier(string $tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
