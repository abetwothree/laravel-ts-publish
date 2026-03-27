<?php

namespace Workbench\App\Http\Resources\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

/**
 * Trait used by both a parent and child resource to exercise the BFS visited-node deduplication.
 */
#[TsExtends('SharedInterface', '@/types/shared')]
trait SharedExtendsInterface {}
