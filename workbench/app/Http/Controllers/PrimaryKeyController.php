<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Models\UuidPost;

class PrimaryKeyController
{
    public function show(UuidPost $uuidPost): void {}
}
