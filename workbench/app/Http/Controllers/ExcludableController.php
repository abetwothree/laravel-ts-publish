<?php

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

class ExcludableController
{
    /** This action is included */
    public function show(): void {}

    /** This action is excluded */
    #[TsExclude]
    public function secret(): void {}
}
