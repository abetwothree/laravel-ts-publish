<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Models\SlugPost;

class CustomRouteKeyController
{
    public function show(SlugPost $slugPost): void {}
}
