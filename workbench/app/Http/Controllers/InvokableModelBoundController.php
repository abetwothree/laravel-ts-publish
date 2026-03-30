<?php

namespace Workbench\App\Http\Controllers;

use Workbench\App\Models\Post;

class InvokableModelBoundController
{
    public function __invoke(Post $post): void {}
}
