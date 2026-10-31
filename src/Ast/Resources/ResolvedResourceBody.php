<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\ResourceReindexing;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

/**
 * What Inertia sends of the keys an API resource's `resolve()` returns. Inertia sends each API resource still inside
 * them as its response, so the resource's own type is published with each key it sends otherwise restated over it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ResolvedResourceBody
{
    use ParsesTsCasts;

    /**
     * The keys a resource's `resolve()` returns as Inertia sends them, or null where its own type already is.
     *
     * @param  class-string<JsonResource>  $resource
     * @return ValueExpressionResult|null
     */
    public function sent(string $resource): ?array
    {
        return resolve(AnalysisMemo::class)->remember('resolved-body:'.$resource, fn (): ?array => $this->sentOnce($resource));
    }

    /**
     * What sent() answers, computed once a run; the memo replays the files recorded here on every later read.
     *
     * @param  class-string<JsonResource>  $resource
     * @return ValueExpressionResult|null
     */
    private function sentOnce(string $resource): ?array
    {
        // The caller's type follows this body.
        DependencyRecorder::recordClass($resource);

        $engine = resolve(AstEngine::class);
        $sent = $this->resolved($engine->analyzeMethod($resource, inertiaProps: true), $resource);
        $restated = $this->restatedKeys($this->resolved($engine->analyzeMethod($resource), $resource), $sent, $resource);

        if ($restated === []) {
            return null;
        }

        foreach (array_diff(array_column($sent->properties, 'name'), $restated) as $name) {
            $sent->forgetChannels($name);
        }

        $sent->properties = array_values(array_filter(
            $sent->properties,
            fn (array $property): bool => in_array($property['name'], $restated, true),
        ));
        $result = resolve(InlineObjectResult::class)->of($sent, [['fqcn' => $resource, 'isModel' => false, 'isCollection' => false]]);
        // The resource's own file imports what its other keys name.
        $imports = ImportMaps::spelledIn($result['customImports'] ?? [], $result['type']);
        unset($result['customImports']);

        return $imports === [] ? $result : [...$result, 'customImports' => $imports];
    }

    /**
     * An analysis of the resource's toArray() as resolve() returns it, re-indexed where its filter() re-indexes.
     *
     * @param  class-string<JsonResource>  $resource
     */
    private function resolved(MethodAnalysis $analysis, string $resource): MethodAnalysis
    {
        if (ResourceCollections::reindexesNumericKeys(new ReflectionClass($resource))) {
            $analysis->rewritePropertyTypes(ResourceReindexing::reindexedType(...));
        }

        return $analysis;
    }

    /**
     * The keys Inertia reads otherwise than the resource publishes them, leaving a cast's key and an index signature.
     *
     * @param  class-string<JsonResource>  $resource
     * @return list<string>
     */
    private function restatedKeys(MethodAnalysis $own, MethodAnalysis $sent, string $resource): array
    {
        $published = array_column($own->properties, null, 'name');
        $cast = $this->castKeys($resource, $sent);
        $keys = [];

        foreach (array_column($sent->properties, null, 'name') as $name => $property) {
            $name = (string) $name;
            $mine = $published[$name] ?? null;

            // A cast's key publishes as declared, and an `Omit<>` cannot name an index signature.
            if (JsEmitter::isIndexSignatureKey($name)
                || in_array($name, $cast, true)
                || ($mine !== null && $mine['type'] === $property['type'] && $mine['optional'] === $property['optional'])
            ) {
                continue;
            }

            $keys[] = $name;
        }

        return $keys;
    }

    /**
     * The keys a #[TsCasts] on its toArray(), its class or its model retypes; no JSON:API resource is read here.
     *
     * @param  class-string<JsonResource>  $resource
     * @return list<string>
     */
    private function castKeys(string $resource, MethodAnalysis $analysis): array
    {
        $reflection = new ReflectionClass($resource);
        $model = resolve(ModelClassResolver::class)->resolve($reflection);

        return array_map(strval(...), [
            ...array_keys($analysis->casts),
            ...array_keys($this->parseTsCastsFromReflection($reflection)['overrides']),
            ...($model === null ? [] : array_keys($this->parseTsCastsFromReflection(new ReflectionClass($model))['overrides'])),
        ]);
    }
}
