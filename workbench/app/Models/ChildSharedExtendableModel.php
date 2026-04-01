<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Workbench\App\Models\Concerns\SharedExtendsTrait;

/**
 * Child model that uses SharedExtendsTrait directly AND extends a parent that also uses it.
 * SharedModelInterface should appear only once despite being reachable via two paths.
 */
class ChildSharedExtendableModel extends BaseSharedExtendableModel
{
    use SharedExtendsTrait;
}
