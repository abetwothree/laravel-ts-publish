<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('HasValidationMeta', '@/types/validation')]
trait HasValidationTimestamps
{
    //
}
