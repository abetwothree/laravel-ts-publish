<?php

namespace Workbench\App\Http\Controllers;

/** Handles named invokable requests */
class NamedInvokableController
{
    public function __invoke(): void {}
}
