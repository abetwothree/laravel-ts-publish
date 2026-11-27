<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;

/**
 * Sees each analysis the walk, the literal's fold and a branch join hand on. The package binds one that does nothing;
 * the test suite binds one that lifts each into an ArrayShape and lowers it back, failing on any field that differs.
 *
 * @internal
 */
interface ShapeObserver
{
    /**
     * See one analysis as it is handed on.
     */
    public function observed(MethodAnalysis $analysis): void;
}
