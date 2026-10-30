<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Tests\TestCase;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Response as InertiaResponse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Expression;

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

/**
 * Analyze one `Controller@action` through the page analyzer.
 *
 * @phpstan-import-type InertiaPageData from InertiaPageAnalyzer
 *
 * @return InertiaPageData|null
 */
function pageData(string $uses): ?array
{
    return new InertiaPageAnalyzer()->analyze(['uses' => $uses]);
}

/**
 * Analyze one fixture middleware without touching the filesystem discovery pass.
 *
 * @phpstan-import-type SharedDataResult from InertiaSharedDataAnalyzer
 *
 * @param  class-string  $middlewareClass
 * @return SharedDataResult|null
 */
function analyzeSharedDataFor(string $middlewareClass): ?array
{
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn($middlewareClass);

    return $analyzer->analyze();
}

/**
 * Each property an API resource publishes, as `name => type`, `?` marking an optional key.
 *
 * @param  class-string<JsonResource>  $class
 * @return array<string, string>
 */
function resourceProps(string $class): array
{
    $props = [];

    foreach (new ResourceTransformer($class)->data()->properties as $name => $property) {
        $props[$name.($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $props;
}

/**
 * Each prop a share middleware publishes, as `name => type`, read from the type the shared data analyzer writes.
 *
 * @param  class-string  $middleware
 * @return array<string, string>
 */
function sharedProps(string $middleware): array
{
    return array_column(TsTypeShape::members(analyzeSharedDataFor($middleware)['sharedPageProps'] ?? '') ?? [], 1, 0);
}

/**
 * What PHP sends for a value, decoded as the frontend reads it.
 */
function sentAsJson(mixed $value): mixed
{
    return json_decode((string) json_encode($value), false);
}

/**
 * The props Inertia sends for a page in answer to a request, made an Inertia visit: a first visit by default, or
 * partialReload()'s request.
 */
function inertiaProps(InertiaResponse $page, ?Request $request = null): stdClass
{
    $request ??= Request::create('/');
    $request->headers->set('X-Inertia', 'true');

    return json_decode((string) $page->toResponse($request)->getContent(), false)->props;
}

/**
 * A request for a partial reload of a component's comma-separated keys.
 */
function partialReload(string $component, string $only): Request
{
    $request = Request::create('/');
    $request->headers->set('X-Inertia-Partial-Component', $component);
    $request->headers->set('X-Inertia-Partial-Data', $only);

    return $request;
}

/** Parse one call, such as `$this->m()` or `self::m()`, written as a statement. */
function runtimeClassCall(string $php): Expr
{
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression);

    return $statement->expr;
}
