<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNullablePayloads;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use ReflectionClass;

/**
 * `new SomeResource(...)`, `new EnumResource($this->prop)`, `new SomeCollection($this->items)`,
 * `new Collection($items)` and a `new` of any other class.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class NewResourceHandler implements ExpressionHandler
{
    use ChecksPreserveKeys;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ReadsNullablePayloads;
    use ResolvesEnumPropertyArgTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [New_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof New_) {
            return $this->analyzeNewResource($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Type `new X(...)`: a resource as itself or null, an EnumResource or a resource collection as what it wraps,
     * `new Collection($items)` as collect($items), and any other class as json_encode() writes it.
     *
     * @return ValueExpressionResult
     */
    private function analyzeNewResource(New_ $expr, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = ValueResult::unknown();

        if (! $expr->class instanceof Name) {
            return $result; // @codeCoverageIgnore
        }

        $className = $this->subjectClassName($expr->class, $scope->subjectReflection);

        // new EnumResource($this->prop)
        if ($this->isEnumResourceClass($className)) {
            $payload = $this->resourcePayloadArguments($expr, $className)->at(0)?->value;

            if ($payload === null) {
                return $result;
            }

            return $this->resolveEnumFromPropertyArg($payload, $scope)
                ?? $this->resolveEnumFromResolvedPayload($payload, $scope, $engine)
                ?? $result;
        }

        // new SomeCollection($this->items) — resolve the collected element type. Must precede the
        // generic isResourceClass() branch below, for the same reason as in analyzeStaticCall().
        if (is_a($className, ResourceCollection::class, true)) {
            $collected = $this->resolveCollectedResourceClass($className);

            if ($collected !== null) {
                return [
                    ...$result,
                    'type' => $this->wrapCollectionElementType(TsNaming::resourceTypeName($collected), new ReflectionClass($className)),
                    'optional' => $this->hasConditionalNewArgument($expr, $className),
                    'resourceFqcn' => $collected,
                ];
            }
        }

        $collect = resolve(ValueResolver::class)->collectCall($expr);

        if ($collect !== null) {
            return $engine->resolve($collect);
        }

        // Any other class reaches JSON as whatever json_encode() writes for it.
        if (! $this->isResourceClass($className)) {
            return resolve(ValueResolver::class)->resolveSerializedNew($expr) ?? $result;
        }

        $type = $this->resourceWrapType($expr, $className, TsNaming::resourceTypeName($className), $scope, $engine);
        $optional = $this->hasConditionalNewArgument($expr, $className);

        /** @var class-string $className */
        return [
            ...$result,
            'type' => $type,
            'optional' => $optional,
            ...($type === 'null' ? [] : ['resourceFqcn' => $className]),
        ];
    }
}
