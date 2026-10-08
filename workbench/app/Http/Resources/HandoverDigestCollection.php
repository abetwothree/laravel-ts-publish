<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * Stacked on a body-less collection and body-less itself: Laravel collects its own `$collects`, under the inherited
 * `handovers` wrap.
 */
class HandoverDigestCollection extends HandoverCollection
{
    public $collects = HandoverSummaryResource::class;
}
