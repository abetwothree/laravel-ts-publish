<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A body-less collection with its own wrap key, which every collection stacked on it inherits.
 */
class HandoverCollection extends ResourceCollection
{
    public static $wrap = 'handovers';

    public $collects = HandoverResource::class;
}
