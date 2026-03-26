<?php

namespace Workbench\App\Models\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

/**
 * Trait used by both a parent and child model to exercise the BFS visited-node deduplication.
 */
#[TsExtends('SharedModelInterface', '@/types/shared-model')]
trait SharedExtendsTrait {}
