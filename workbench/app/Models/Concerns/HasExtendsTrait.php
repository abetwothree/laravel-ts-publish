<?php

declare(strict_types=1);

namespace Workbench\App\Models\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('TraitInterface', '@/types/model-trait')]
trait HasExtendsTrait {}
