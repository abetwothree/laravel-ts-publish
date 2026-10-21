<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;
use ReflectionMethod;

/**
 * Laravel's guess of the API resource a model's `toResource()` builds, and of the collection a `toResourceCollection()`
 * builds, from the model its receiver holds.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type RelationTypeInfo from ModelAttributeResolver
 *
 * @phpstan-type GuessedCollection = array{collection: class-string<ResourceCollection>, resource: null}|array{collection: null, resource: class-string}
 *
 * @internal
 */
trait GuessesModelResources
{
    /**
     * Whether a class exists and is a JsonResource subclass.
     */
    abstract protected function isResourceClass(string $fqcn): bool;

    /**
     * Whether a class is an API resource this run will also emit a file for.
     */
    abstract protected function isPublishedResourceClass(string $fqcn): bool;

    /**
     * Whether an expression is a `$this->prop` property fetch, whichever property it names.
     */
    abstract protected function isThisPropertyFetch(Expr $expr): bool;

    /**
     * Resolve a `$this->{name}` property as a relation on the scope's backing model.
     *
     * @return RelationTypeInfo
     */
    abstract protected function resolveModelRelationTypeInfo(string $name, AnalysisScope $scope): array;

    /**
     * Analyze `$model->toResource()` / `$model->toResource(SomeResource::class)`. An explicit
     * argument wins outright; otherwise the receiver's model resolves via resolveResourceForModel(). Each resource
     * is typed as JsonApiResources::instanceType() sends it.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeToResourceCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $args = $this->resourceCallArguments($call, Model::class, 'toResource');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            if ($explicit === null || ! $this->isResourceClass($explicit)) {
                return $result;
            }

            /** @var class-string $explicit */
            return [...$result, 'type' => JsonApiResources::instanceType($explicit), 'optional' => false, 'resourceFqcn' => $explicit];
        }

        $models = $this->resolveToResourceReceiverModels($call->var, $scope);

        if (count($models) > 1) {
            $resourceFqcns = [];

            foreach ($models as $model) {
                $resolved = $this->resolveResourceForModel($model);

                // All or nothing: a union missing an arm publishes a type that is wrong for that arm.
                if ($resolved === null) {
                    return $result;
                }

                $resourceFqcns[] = $resolved;
            }

            $resourceFqcns = array_values(array_unique($resourceFqcns));

            return [
                ...$result,
                'type' => implode(' | ', array_map(JsonApiResources::instanceType(...), $resourceFqcns)),
                'optional' => false,
                'embeddedResourceFqcns' => $resourceFqcns,
            ];
        }

        $modelFqcn = $models[0] ?? null;
        $resourceFqcn = $modelFqcn !== null ? $this->resolveResourceForModel($modelFqcn) : null;

        if ($resourceFqcn === null) {
            return $result;
        }

        return [...$result, 'type' => JsonApiResources::instanceType($resourceFqcn), 'optional' => false, 'resourceFqcn' => $resourceFqcn];
    }

    /**
     * The call's arguments mapped against Laravel's own toResource()/toResourceCollection() signature. On a
     * Laravel release without the method only positions are known, which the `?? at(0)` fallback reads.
     *
     * @param  class-string  $class
     */
    protected function resourceCallArguments(MethodCall|NullsafeMethodCall $call, string $class, string $method): CallArguments
    {
        if (method_exists($class, $method)) {
            return CallArguments::for($call, new ReflectionMethod($class, $method));
        }

        return CallArguments::fromNames($call->isFirstClassCallable() ? [] : $call->getArgs(), []);
    }

    /**
     * Resolve the model class backing a toResource()/toResourceCollection() receiver: a whenLoaded closure parameter
     * (ConditionalMethodHandler::analyzeWhenLoaded()'s bindings) or `$this->relation` accessed directly. Past the write
     * that ended a parameter's binding, the receiver resolver reads what the body assigned instead.
     *
     * @return class-string<Model>|null
     */
    protected function resolveToResourceReceiverModel(Expr $receiver, AnalysisScope $scope): ?string
    {
        if ($receiver instanceof Variable && is_string($receiver->name)) {
            if (! $scope->holdsBinding($receiver)) {
                return null;
            }

            return $scope->varModelBindings[$receiver->name]
                ?? $scope->varCollectionBindings[$receiver->name]['modelFqcn']
                ?? null;
        }

        if ($receiver instanceof PropertyFetch && $this->isThisPropertyFetch($receiver) && $receiver->name instanceof Identifier) {
            return $this->resolveModelRelationTypeInfo($receiver->name->toString(), $scope)['modelFqcn'];
        }

        return null;
    }

    /**
     * Reproduce Model::toResource()'s guessResource(): the #[UseResource] attribute first, then
     * the naming-convention candidates, Resource-suffixed candidate first. Read once per model a run.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return class-string|null
     */
    protected function resolveResourceForModel(string $modelFqcn): ?string
    {
        return resolve(AnalysisMemo::class)->remember('model-resource:'.$modelFqcn, function () use ($modelFqcn): ?string {
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
        });
    }

    /**
     * Reproduce Collection::toResourceCollection()'s guessResourceCollection() order, #[UseResourceCollection], then
     * #[UseResource], then the naming convention trying `{Guessed}Collection` before the bare guessed resources: the
     * collection class Laravel builds, or the resource whose `::collection()` it calls. Read once per model a run.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return GuessedCollection|null
     */
    protected function resolveResourceCollectionForModel(string $modelFqcn): ?array
    {
        return resolve(AnalysisMemo::class)->remember(
            'model-resource-collection:'.$modelFqcn,
            fn (): ?array => $this->guessResourceCollectionForModel($modelFqcn),
        );
    }

    /**
     * The guess resolveResourceCollectionForModel() stores for the run.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return GuessedCollection|null
     */
    private function guessResourceCollectionForModel(string $modelFqcn): ?array
    {
        // Vendor returns `new $useResourceCollection($this)` unconditionally once the attribute
        // names an existing class — it never falls through to #[UseResource] or the naming convention.
        $collectionFqcn = $this->resolveUseResourceCollectionAttribute($modelFqcn);

        if ($collectionFqcn !== null) {
            return ['collection' => $collectionFqcn, 'resource' => null];
        }

        $resourceFqcn = $this->resolveUseResourceAttribute($modelFqcn);

        if ($resourceFqcn !== null) {
            return ['collection' => null, 'resource' => $resourceFqcn];
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
                return ['collection' => $collectionCandidate, 'resource' => null];
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->isPublishedResourceClass($candidate)) {
                return ['collection' => null, 'resource' => $candidate];
            }
        }

        return null;
    }

    /**
     * Every model a toResource() receiver may hold: the single-model bindings first, then the receiver resolver.
     *
     * @return list<class-string<Model>>
     */
    private function resolveToResourceReceiverModels(Expr $receiver, AnalysisScope $scope): array
    {
        $single = $this->resolveToResourceReceiverModel($receiver, $scope);

        return $single !== null ? [$single] : (resolve(ReceiverClassResolver::class)->resolve($receiver, $scope)?->models() ?? []);
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
     * @return class-string<ResourceCollection>|null
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
