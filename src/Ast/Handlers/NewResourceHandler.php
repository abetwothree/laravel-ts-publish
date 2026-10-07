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
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use ReflectionClass;

/**
 * `new SomeResource(...)`, `new EnumResource($this->prop)`, and `new SomeCollection($this->items)`.
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
     * Analyze `new SomeResource(...)` — resolve as a nested resource.
     *
     * @return ValueExpressionResult
     */
    private function analyzeNewResource(New_ $expr, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = ValueResult::unknown();

        if (! $expr->class instanceof Name) {
            return $result; // @codeCoverageIgnore
        }

        $className = $expr->class->toString();

        // Resolve `self`/`static` so `new self(...)` is treated identically to `new ClassName(...)`.
        if ($className === 'self' || $className === 'static') {
            $className = $scope->subjectReflection->getName();
        }

        // new EnumResource($this->prop)
        if ($this->isEnumResourceClass($className)) {
            $payload = $this->resourcePayloadArguments($expr, $className)->at(0)?->value;

            if ($payload === null) {
                return $result;
            }

            return $this->resolveEnumFromPropertyArg($payload, $scope)
                ?? $this->resolveEnumFromResolvedPayload($payload, $engine)
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

        if (! $this->isResourceClass($className)) {
            return $result; // @codeCoverageIgnore
        }

        $resourceName = TsNaming::resourceTypeName($className);
        $optional = $this->hasConditionalNewArgument($expr, $className);

        /** @var class-string $className */
        return [
            ...$result,
            'type' => $this->wrapsNullablePayload($expr, $className, $scope, $engine) ? ValueResult::withNullArm($resourceName) : $resourceName,
            'optional' => $optional,
            'resourceFqcn' => $className,
        ];
    }
}
