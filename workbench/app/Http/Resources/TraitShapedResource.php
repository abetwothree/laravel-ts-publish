<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\ShapesTraitPayload;
use Workbench\App\Models\Label;

/**
 * Takes its toArray() from a trait declared in another file, which is still the resource's own method.
 *
 * @mixin Label
 */
class TraitShapedResource extends JsonResource
{
    use ShapesTraitPayload;
}
