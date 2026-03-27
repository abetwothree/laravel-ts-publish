<?php

namespace Workbench\App\Http\Resources\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('ExtendableInterface')]
#[TsExtends('Omit<Timestamps, "created_at" | "updated_at">', '@/types/util', ['Timestamps'])]
trait ExtendsInterfaces {}
