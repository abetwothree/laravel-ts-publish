<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\GuessesModelResources;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\EmptyReceiverGuess;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use ReflectionClass;

/**
 * `$model->toResource()` / `$model?->toResource()` / `$model->toResource(SomeResource::class)` and
 * `$collection->toResourceCollection()` / `->toResourceCollection(SomeResource::class)`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ToResourceHandler implements ExpressionHandler
{
    use GuessesModelResources;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) || ! $expr->name instanceof Identifier) {
            return null;
        }

        // A whenLoaded closure param bound to a model, or `$this->relation` accessed directly: one path for both
        // receiver shapes, see resolveToResourceReceiverModel().
        $result = match ($expr->name->toString()) {
            'toResource' => $this->analyzeToResourceCall($expr, $scope),
            'toResourceCollection' => $this->analyzeToResourceCollectionCall($expr, $scope),
            default => null,
        };

        // A `?->` here or earlier in the chain ends the whole expression as null, where a plain `->` on null throws.
        return $result !== null && $this->chainShortCircuits($expr)
            ? [...$result, 'type' => ValueResult::withNullArm($result['type'])]
            : $result;
    }

    /**
     * Analyze `$collection->toResourceCollection()` / `->toResourceCollection(SomeResource::class)`.
     * An explicit argument wins outright; otherwise the receiver's model, else the element model the receiver resolver
     * reads, resolves via resolveResourceCollectionForModel(), beside what EmptyReceiverGuess nests for no items.
     *
     * @return ValueExpressionResult
     */
    private function analyzeToResourceCollectionCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $args = $this->resourceCallArguments($call, Collection::class, 'toResourceCollection');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            if ($explicit === null || ! $this->isResourceClass($explicit)) {
                return $result;
            }

            /** @var class-string $explicit */
            return [
                ...$result,
                'type' => ResourceCollections::listType(TsNaming::resourceTypeName($explicit), new ReflectionClass($explicit)),
                'optional' => false,
                'resourceFqcn' => $explicit,
            ];
        }

        $modelFqcn = $this->resolveToResourceReceiverModel($call->var, $scope)
            ?? resolve(ReceiverClassResolver::class)->resolve($call->var, $scope)?->elementModel;
        $resolved = $modelFqcn !== null ? $this->resolveResourceCollectionForModel($modelFqcn) : null;

        if ($resolved === null) {
            return $result;
        }

        if ($resolved['collection'] !== null) {
            $nested = resolve(EmptyReceiverGuess::class)->nestedValue($resolved['collection']);

            return $nested === null ? $result : [...$nested, 'optional' => false];
        }

        $resource = $resolved['resource'];

        return [
            ...$result,
            'type' => ResourceCollections::listType(TsNaming::resourceTypeName($resource), new ReflectionClass($resource)),
            'optional' => false,
            'resourceFqcn' => $resource,
        ];
    }
}
