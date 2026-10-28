<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Tests\TestCase;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;

uses(TestCase::class)->in(__DIR__);

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function chainHandlersThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        /** Fails the test: no sub-expression is resolved in this case. */
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        /** Fails the test: no method is spread in this case. */
        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Calls no callable it reads. */
        public function invokesCallables(): bool
        {
            return false;
        }

        /** Fails the test: no array is analyzed in this case. */
        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/**
 * Each property an API resource publishes, as `name => type`, `?` marking an optional key, with the numeric-keyed
 * arrays re-indexed into the lists the resource sends.
 *
 * @param  class-string  $resource
 * @param  class-string  $model
 * @return array<string, string>
 */
function publishedResourceKeyTypes(string $resource, string $model): array
{
    $keys = [];

    foreach (resolve(AstEngine::class)->analyze($resource, 'toArray', $model)->properties as $property) {
        $keys[$property['name'].($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $keys;
}

/**
 * The keys analyze() returns, each suffixed `?` when optional, mapped to its type.
 *
 * @param  class-string  $class
 * @return array<string, string>
 */
function analyzedKeyTypes(string $class, string $method = 'toArray'): array
{
    $keys = [];

    foreach (resolve(AstEngine::class)->analyze($class, $method)->properties as $property) {
        $keys[$property['name'].($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $keys;
}

/**
 * Every type name analyze()'s typeImports import, sorted.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function analyzedImportNames(string $class, string $method = 'toArray'): array
{
    $names = array_merge(...array_values(resolve(AstEngine::class)->analyze($class, $method)->typeImports));
    sort($names);

    return $names;
}

/** Analyze one `Controller@action` through the page analyzer. */
function pageData(string $uses): ?array
{
    return new InertiaPageAnalyzer()->analyze(['uses' => $uses]);
}
