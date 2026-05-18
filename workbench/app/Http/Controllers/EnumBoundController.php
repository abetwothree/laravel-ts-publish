<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Enums\Status;

class EnumBoundController
{
    public function byStatus(Status $status): void {}
}
