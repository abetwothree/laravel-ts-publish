<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;
use ReflectionMethod;

/**
 * `$model->toResource()` / `$model->toResource(SomeResource::class)` and
 * `$collection->toResourceCollection()` / `->toResourceCollection(SomeResource::class)`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ToResourceHandler implements ExpressionHandler
{
    use ChecksPreserveKeys;
    use InspectsAstNodes;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // $model->toResource()/toResourceCollection() — a whenLoaded closure param bound to a
        // model, or $this->relation accessed directly. Checked by method name alone so both
        // receiver shapes share one resolution path; see resolveToResourceReceiverModel().
        if ($expr instanceof MethodCall && $expr->name instanceof Identifier && $expr->name->toString() === 'toResource') {
            return $this->analyzeToResourceCall($expr, $scope);
        }

        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'toResourceCollection'
        ) {
            return $this->analyzeToResourceCollectionCall($expr, $scope);
        }

        return null;
    }

    /**
     * Analyze `$model->toResource()` / `$model->toResource(SomeResource::class)`. An explicit
     * argument wins outright; otherwise the receiver's model resolves via resolveResourceForModel().
     *
     * @return ValueExpressionResult
     */
    private function analyzeToResourceCall(MethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $args = $this->callArguments($call, Model::class, 'toResource');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            if ($explicit === null || ! $this->isResourceClass($explicit)) {
                return $result;
            }

            /** @var class-string $explicit */
            return [...$result, 'type' => LaravelTsPublish::resourceTypeName($explicit), 'optional' => false, 'resourceFqcn' => $explicit];
        }

        $modelFqcn = $this->resolveToResourceReceiverModel($call->var, $scope);
        $resourceFqcn = $modelFqcn !== null ? $this->resolveResourceForModel($modelFqcn) : null;

        if ($resourceFqcn === null) {
            return $result;
        }

        return [...$result, 'type' => LaravelTsPublish::resourceTypeName($resourceFqcn), 'optional' => false, 'resourceFqcn' => $resourceFqcn];
    }

    /**
     * Analyze `$collection->toResourceCollection()` / `->toResourceCollection(SomeResource::class)`.
     * An explicit argument wins outright; otherwise the receiver's model resolves via
     * resolveResourceCollectionForModel().
     *
     * @return ValueExpressionResult
     */
    private function analyzeToResourceCollectionCall(MethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $args = $this->callArguments($call, Collection::class, 'toResourceCollection');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            if ($explicit === null || ! $this->isResourceClass($explicit)) {
                return $result;
            }

            /** @var class-string $explicit */
            return [
                ...$result,
                'type' => $this->wrapCollectionElementType(LaravelTsPublish::resourceTypeName($explicit), new ReflectionClass($explicit)),
                'optional' => false,
                'resourceFqcn' => $explicit,
            ];
        }

        $modelFqcn = $this->resolveToResourceReceiverModel($call->var, $scope);
        $resolved = $modelFqcn !== null ? $this->resolveResourceCollectionForModel($modelFqcn) : null;

        if ($resolved === null) {
            return $result;
        }

        return [
            ...$result,
            'type' => $this->wrapCollectionElementType(
                LaravelTsPublish::resourceTypeName($resolved['resourceFqcn']),
                new ReflectionClass($resolved['collectionFqcn']),
            ),
            'optional' => false,
            'resourceFqcn' => $resolved['resourceFqcn'],
        ];
    }

    /**
     * The call's arguments mapped against Laravel's own toResource()/toResourceCollection() signature. On a
     * Laravel release without the method only positions are known, which the `?? at(0)` fallback reads.
     *
     * @param  class-string  $class
     */
    private function callArguments(MethodCall $call, string $class, string $method): CallArguments
    {
        if (method_exists($class, $method)) {
            return CallArguments::for($call, new ReflectionMethod($class, $method));
        }

        return CallArguments::fromNames($call->isFirstClassCallable() ? [] : $call->getArgs(), []);
    }

    /**
     * Resolve the model class backing a toResource()/toResourceCollection() receiver: a whenLoaded
     * closure parameter (ConditionalMethodHandler::analyzeWhenLoaded()'s bindings) or
     * `$this->relation` accessed directly.
     *
     * @return class-string<Model>|null
     */
    private function resolveToResourceReceiverModel(Expr $receiver, AnalysisScope $scope): ?string
    {
        if ($receiver instanceof Variable && is_string($receiver->name)) {
            return $scope->varModelBindings[$receiver->name]
                ?? $scope->varCollectionBindings[$receiver->name]['modelFqcn']
                ?? $scope->closureRelationModelClass;
        }

        if ($receiver instanceof PropertyFetch && $this->isThisPropertyFetch($receiver) && $receiver->name instanceof Identifier) {
            return $this->resolveModelRelationTypeInfo($receiver->name->toString(), $scope)['modelFqcn'];
        }

        return null;
    }

    /**
     * Reproduce Model::toResource()'s guessResource(): the #[UseResource] attribute first, then
     * the naming-convention candidates, Resource-suffixed candidate first.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return class-string|null
     */
    private function resolveResourceForModel(string $modelFqcn): ?string
    {
        $fromAttribute = $this->resolveUseResourceAttribute($modelFqcn);

        if ($fromAttribute !== null) {
            return $fromAttribute;
        }

        foreach ($this->guessResourceNames($modelFqcn) as $candidate) {
            if ($this->isPublishedResourceClass($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Reproduce Collection::toResourceCollection()'s guessResourceCollection() order: the
     * #[UseResourceCollection] attribute, then #[UseResource], then the naming convention —
     * trying `{Guessed}Collection` classes before the bare guessed resources.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return array{collectionFqcn: class-string, resourceFqcn: class-string}|null
     */
    private function resolveResourceCollectionForModel(string $modelFqcn): ?array
    {
        // Vendor returns `new $useResourceCollection($this)` unconditionally once the attribute
        // names an existing class — it never falls through to #[UseResource] or the naming
        // convention, even when the element type can't be determined here. Match that: stop hard.
        $collectionFqcn = $this->resolveUseResourceCollectionAttribute($modelFqcn);

        if ($collectionFqcn !== null) {
            $resourceFqcn = $this->resolveCollectedResourceClass($collectionFqcn);

            return $resourceFqcn !== null
                ? ['collectionFqcn' => $collectionFqcn, 'resourceFqcn' => $resourceFqcn]
                : null;
        }

        $resourceFqcn = $this->resolveUseResourceAttribute($modelFqcn);

        if ($resourceFqcn !== null) {
            return ['collectionFqcn' => $resourceFqcn, 'resourceFqcn' => $resourceFqcn];
        }

        $candidates = $this->guessResourceNames($modelFqcn);

        // Same shape here: vendor's own loop returns `new $resourceCollection($this)` the moment
        // `class_exists($resourceCollection)` passes for a candidate, never trying the next one.
        foreach ($candidates as $candidate) {
            $collectionCandidate = $candidate.'Collection';

            if (class_exists($collectionCandidate)
                && is_a($collectionCandidate, ResourceCollection::class, true)
                && PublishedResourceRegistry::isPublished($collectionCandidate)
            ) {
                $collectedFqcn = $this->resolveCollectedResourceClass($collectionCandidate);

                return $collectedFqcn !== null
                    ? ['collectionFqcn' => $collectionCandidate, 'resourceFqcn' => $collectedFqcn]
                    : null;
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->isPublishedResourceClass($candidate)) {
                return ['collectionFqcn' => $candidate, 'resourceFqcn' => $candidate];
            }
        }

        return null;
    }

    /**
     * Reproduce Model::guessResourceName()'s `\Models\` to `\Http\Resources\` naming convention.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return list<class-string>
     */
    private function guessResourceNames(string $modelFqcn): array
    {
        if (! str_contains($modelFqcn, '\\Models\\')) {
            return [];
        }

        $basename = class_basename($modelFqcn);
        $relativeNamespace = Str::after($modelFqcn, '\\Models\\');

        $relativeNamespace = str_contains($relativeNamespace, '\\')
            ? Str::beforeLast($relativeNamespace, '\\'.$basename)
            : '';

        $potentialResource = sprintf(
            '%s\\Http\\Resources\\%s%s',
            Str::before($modelFqcn, '\\Models'),
            $relativeNamespace !== '' ? $relativeNamespace.'\\' : '',
            $basename,
        );

        /** @var list<class-string> */
        return [$potentialResource.'Resource', $potentialResource];
    }

    /**
     * Read the #[UseResource] attribute directly off a model class.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return class-string|null
     */
    private function resolveUseResourceAttribute(string $modelFqcn): ?string
    {
        $attributeFqcn = 'Illuminate\Database\Eloquent\Attributes\UseResource';

        if (! class_exists($attributeFqcn) || ! class_exists($modelFqcn)) {
            return null;
        }

        $attributes = new ReflectionClass($modelFqcn)->getAttributes($attributeFqcn);

        if ($attributes === []) {
            return null;
        }

        $resourceFqcn = $attributes[0]->newInstance()->class;

        return $this->isResourceClass($resourceFqcn) ? $resourceFqcn : null;
    }

    /**
     * Read the #[UseResourceCollection] attribute directly off a model class.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return class-string|null
     */
    private function resolveUseResourceCollectionAttribute(string $modelFqcn): ?string
    {
        $attributeFqcn = 'Illuminate\Database\Eloquent\Attributes\UseResourceCollection';

        if (! class_exists($attributeFqcn) || ! class_exists($modelFqcn)) {
            return null;
        }

        $attributes = new ReflectionClass($modelFqcn)->getAttributes($attributeFqcn);

        if ($attributes === []) {
            return null;
        }

        $collectionFqcn = $attributes[0]->newInstance()->class;

        return class_exists($collectionFqcn) && is_a($collectionFqcn, ResourceCollection::class, true)
            ? $collectionFqcn
            : null;
    }
}
