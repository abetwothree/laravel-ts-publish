<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;

/**
 * The observer a publish runs with: it sees nothing, so no check slows the read.
 *
 * @internal
 */
final class NullShapeObserver implements ShapeObserver
{
    /**
     * Do nothing.
     */
    public function observed(MethodAnalysis $analysis): void {}
}
