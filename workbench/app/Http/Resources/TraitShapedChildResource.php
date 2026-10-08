<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Workbench\App\Models\Label;

/**
 * Inherits a toArray() its parent takes from a trait, so the analysis walks to the parent and reads the trait there.
 *
 * @mixin Label
 */
final class TraitShapedChildResource extends TraitShapedResource {}
