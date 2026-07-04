<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Models\Post;

class InvokableModelBoundPlusController
{
    public function __invoke(Post $post): void {}

    public function extra(Post $post): void {}

    public function surprise(Post $post): void {}
}
