<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;

/**
 * A located class method: its reflection, its ClassMethod AST node, and the parsed file it lives in.
 *
 * @internal
 */
final readonly class MethodContext
{
    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  array<Node>  $fileStmts
     */
    public function __construct(
        public ReflectionClass $reflection,
        public ClassMethod $method,
        public array $fileStmts,
    ) {}
}
