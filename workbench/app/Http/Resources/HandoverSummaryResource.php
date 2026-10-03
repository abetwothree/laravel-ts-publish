<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * Declares no toArray(), so it publishes the model's own serialization, appended `parties` accessor included: the
 * union of two same-named models inside it has to survive the delegation with each token naming its own class.
 *
 * @mixin Handover
 */
class HandoverSummaryResource extends JsonResource {}
