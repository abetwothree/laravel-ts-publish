<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

#[TsExclude]
class ExcludedController
{
    public function index(): void {}
}
