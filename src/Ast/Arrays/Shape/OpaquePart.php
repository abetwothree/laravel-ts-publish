<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use PhpParser\Node;

/**
 * Keys an array may hold that no name the engine reads names, beside the site that brings them. An unnamed `unset()`
 * is never one: it may only take keys away.
 *
 * @internal
 */
final readonly class OpaquePart
{
    /**
     * Hold the site that brings the keys and why they are unnamed.
     */
    public function __construct(
        public Node $site,
        public OpaqueKind $kind,
    ) {}
}
