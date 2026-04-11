<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Enums\Role;

class TypedParamController
{
    public function showInt(int $id): void {}

    public function showRole(Role $role): void {}
}
