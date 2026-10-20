<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;

/**
 * Result of AST analysis of a resource's toArray() method; see MethodAnalysis for the shared DTO shape.
 *
 * @internal
 */
class ResourceAnalysis extends MethodAnalysis {}
