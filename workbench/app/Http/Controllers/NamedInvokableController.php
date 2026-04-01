<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

/** Handles named invokable requests */
class NamedInvokableController
{
    public function __invoke(): void {}
}
