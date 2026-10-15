<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use ReflectionClass;
use ReflectionMethod;

/**
 * Types a method call from the PHP classes its receiver holds; the rules are in
 * docs/components/receiver-types.md § Following a method's return type.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverMethodReturnResolver
{
    use FiltersAttributeKeys;
    use ResolvesFilteredRelationTypes;

    /** Model methods that return the receiver's own class or null, though Laravel declares `Model|null`. */
    private const array ROUTE_BINDINGS = ['resolveRouteBinding', 'resolveSoftDeletableRouteBinding'];

    /** What a resource collection's items answer, whichever class holds them: the held value, an item, the list. */
    private const array ITEMS_VALUE = ['toArray', 'jsonSerialize'];

    private const array ITEMS_ELEMENT = ['first', 'last'];

    private const array ITEMS_LIST = ['items', 'getCollection'];

    private const array ITEMS_SUBSET = ['only', 'except'];

    /**
     * What Model's own methods declared a bare `array` hold: a list of names, or a map keyed by name. all() lists every
     * row of the class, each serialized as the loaded relations decide.
     */
    private const array MODEL_ARRAY_RETURNS = [
        'getAppends' => 'string[]',
        'getMutatedAttributes' => 'string[]',
        'getTouchedRelations' => 'string[]',
        'uniqueIds' => 'string[]',
        'getQueueableRelations' => 'string[]',
        'guessResourceName' => 'string[]',
        'qualifyColumns' => 'string[]',
        'resolveGlobalScopeAttributes' => 'string[]',
        'resolveObserveAttributes' => 'string[]',
        'getCasts' => 'Record<string, string>',
        'getGlobalScopes' => 'Record<string, unknown>',
        'getRelations' => 'Record<string, unknown>',
        'relationsToArray' => 'Record<string, unknown>',
        'getAllGlobalScopes' => 'Record<string, Record<string, unknown>>',
        'all' => 'unknown[]',
    ];

    /**
     * Type a method on every class the receiver holds, unioning the answers.
     *
     * @param  bool  $fromInside  a non-public method is reachable, as through `self::`, `static::` or `parent::`; only
     *                            a direct caller passes it: StaticCallHandler answers every named-class call first
     * @param  MethodCall|NullsafeMethodCall|StaticCall|null  $call  the call node, for a rule that reads its arguments
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null null when any receiver class cannot type the method, or none can run it
     */
    public function resolve(
        ReceiverType $receiver,
        string $methodName,
        AnalysisScope $scope,
        bool $fromInside = false,
        MethodCall|NullsafeMethodCall|StaticCall|null $call = null,
        ?CallSite $site = null,
    ): ?array {
        $results = [];

        if ($receiver->elementModel !== null) {
            DependencyRecorder::recordClass($receiver->elementModel);
        }

        foreach ($receiver->classes as $class) {
            // The answer, a decline included, follows the class's declarations, which reflection reads without parsing.
            DependencyRecorder::recordClass($class);

            // Where a collection's items cannot run the method the call throws, so no response carries that arm.
            if ($receiver->collectedItems && ! $this->itemsHolderRuns($class, $methodName)) {
                continue;
            }

            $result = $this->ruleFor($receiver, $class, $methodName, $scope, $call)
                ?? $this->resolveOn($class, $methodName, $fromInside, $site);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        if ($results === []) {
            return null;
        }

        $result = count($results) === 1
            ? $results[0]
            : ValueResult::unionResults($results);

        return in_array($methodName, $this->supportedAttributeFilters(), true) && ! $scope->carriesImports
            ? $this->filterReturnWithoutImports($result, $methodName, $call)
            : $result;
    }

    /**
     * Whether the filter answers type a model's only()/except(): it runs Model's own, or overrides it with a return
     * reflection cannot type, such as `return parent::only($attributes)`. A typed override publishes its own return.
     *
     * RelationFilterHandler asks the same, so a relation, accessor or map proxy to such a model agrees with this rule.
     *
     * @param  class-string  $class
     */
    public function typesAsModelFilter(string $class, string $methodName): bool
    {
        return $this->runsModelFilter($class, $methodName)
            || (is_a($class, Model::class, true) && $this->resolveOn($class, $methodName, false) === null);
    }

    /**
     * The one Eloquent model a receiver holds when every class it holds is that model; null otherwise.
     *
     * @return class-string<Model>|null
     */
    public function singleModel(ReceiverType $receiver): ?string
    {
        $models = $receiver->models();

        return count($models) === 1 && $models === $receiver->classes ? $models[0] : null;
    }

    /**
     * A resource collection's items as a value, one arm per class that may hold them, as Laravel serializes each: the
     * collected API resource's list for Support\Collection, the `@tolki/types` paginator over it for a paginator.
     * The element is `unknown` when the collection collects no API resource.
     *
     * @return ValueExpressionResult|null null for a receiver that holds no resource collection's items
     */
    public function heldItems(ReceiverType $receiver, AnalysisScope $scope): ?array
    {
        if (! $receiver->collectedItems) {
            return null;
        }

        $element = $receiver->elementResource;
        $name = $element === null ? 'unknown' : TsNaming::resourceTypeName($element);
        $arms = [];
        $paginators = [];

        foreach ($receiver->classes as $class) {
            $paginator = TolkiTypes::MAP[$class] ?? null;

            if ($paginator === null) {
                $arms[] = ValueResult::collectionListType($name, ! $scope->reindexesNumericKeys);

                continue;
            }

            $arms[] = "{$paginator}<{$name}>";
            $paginators[] = $paginator;
        }

        $result = [...ValueResult::unknown(), 'type' => implode(' | ', $arms)];
        $result = $element === null ? $result : [...$result, 'resourceFqcn' => $element];

        return $paginators === [] ? $result : [...$result, 'customImports' => ['@tolki/types' => $paginators]];
    }

    /**
     * Whether a rule types a model's toArray() or one of its loosely declared arrays, which it runs as Model's own.
     *
     * A handler that reads a declaration directly, as for `$this->resource->m()`, leaves such a call to these rules.
     *
     * @param  class-string  $class
     */
    public function typesModelMethod(string $class, string $methodName): bool
    {
        DependencyRecorder::recordClass($class);

        return ($methodName === 'toArray' || isset(self::MODEL_ARRAY_RETURNS[$methodName])) && $this->runsModelMethod($class, $methodName);
    }

    /**
     * Convention rules that need the receiver's model rather than a reflected signature.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function ruleFor(
        ReceiverType $receiver,
        string $class,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        $items = $receiver->collectedItems ? $this->itemsRule($receiver, $class, $methodName, $scope, $call) : null;

        if ($items !== null) {
            return $items;
        }

        $resolver = resolve(ModelAttributeResolver::class);

        // An abstract model answers with the key type it declares, which a subclass that changes it does not keep.
        if ($methodName === 'getKey' && $this->inheritsModelMethod($class, $methodName)) {
            return $this->columnResult($receiver, $resolver->keyTsType($class));
        }

        // getAuthIdentifier() reads the column getAuthIdentifierName() names; an override of it is read as declared.
        if ($methodName === 'getAuthIdentifier'
            && is_subclass_of($class, Model::class)
            && method_exists($class, $methodName)) {
            return $this->columnResult($receiver, $resolver->authIdentifierTsType($class));
        }

        // resolveRouteBindingQuery() queries the receiver itself, so first() holds its own class.
        if (in_array($methodName, self::ROUTE_BINDINGS, true) && $this->runsModelMethod($class, $methodName)) {
            $bound = $this->selfType($class, nullable: true);

            return $bound !== null && ValueResult::namesOnlyPublishedModels($bound) ? $bound : null;
        }

        if ($methodName === 'modelKeys' && $receiver->elementModel !== null) {
            $keyType = $resolver->keyTsType($receiver->elementModel);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => ValueResult::arrayWrapType($keyType)];
        }

        // Its keys hang on the relations loaded at runtime, so only an attribute-keyed object is sure.
        if ($methodName === 'toArray' && $this->runsModelMethod($class, $methodName)) {
            return $this->attributeRecordResult(nullable: false);
        }

        if (isset(self::MODEL_ARRAY_RETURNS[$methodName]) && $this->runsModelMethod($class, $methodName)) {
            return [...ValueResult::unknown(), 'type' => self::MODEL_ARRAY_RETURNS[$methodName]];
        }

        // A relation's collection is keyed 0..n-1, so all() is the list of its models.
        if ($methodName === 'all' && $receiver->elementModel !== null) {
            $models = [
                ...ValueResult::unknown(),
                'type' => ValueResult::arrayWrapType(class_basename($receiver->elementModel)),
                'modelFqcn' => $receiver->elementModel,
            ];

            return ValueResult::namesOnlyPublishedModels($models) ? $models : null;
        }

        // A relation's collection is keyed 0..n-1, so it lists what each model's own toArray() writes.
        if ($methodName === 'toArray' && $receiver->elementModel !== null) {
            $element = $this->runsModelMethod($receiver->elementModel, $methodName) ? 'Record<string, unknown>' : 'unknown';

            return [...ValueResult::unknown(), 'type' => ValueResult::arrayWrapType($element)];
        }

        if (! in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return null;
        }

        if ($this->runsCollectionFilter($class, $methodName)) {
            return $this->attributeRecordResult(nullable: false);
        }

        return $this->typesAsModelFilter($class, $methodName) ? $this->attributeFilterRule($receiver, $methodName, $scope, $call) : null;
    }

    /**
     * A column's type as a result, adding `| null` where no row backs the receiver yet; null when there is no type.
     *
     * @return ValueExpressionResult|null
     */
    private function columnResult(ReceiverType $receiver, ?string $type): ?array
    {
        return $type === null
            ? null
            : [...ValueResult::unknown(), 'type' => $receiver->unsaved ? ValueResult::withNullArm($type) : $type];
    }

    /**
     * `only()`/`except()` on a receiver holding one model class, built from RelationFilterHandler's helpers so the two
     * agree wherever both claim a call. It also answers a literal list naming nothing typed, which that handler
     * declines; a StaticCall carries no key list this can read, so it declines.
     *
     * @return ValueExpressionResult|null
     */
    private function attributeFilterRule(
        ReceiverType $receiver,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        if (! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall) {
            return null;
        }

        $model = $this->singleModel($receiver);

        if ($model === null) {
            return null;
        }

        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return $this->attributeRecordResult(nullable: false);
        }

        $result = $this->literalKeyFilterResult($model, $keys, $methodName === 'only', $scope->carriesImports);

        // Model::only() keys a name it cannot find to null, so the value is still an attribute-keyed array.
        if ($result === null) {
            return $this->attributeRecordResult(nullable: false);
        }

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * An `only()`/`except()` answer where the scope imports nothing, such as an override declaring `: static`.
     * The body fallback drops a whole shape naming a token, so a top-level model arm, or a list of one, is spelled as
     * the object it serializes to; any other token, such as an enum or a nested model, leaves `unknown`.
     *
     * @param  ValueExpressionResult  $result
     * @param  MethodCall|NullsafeMethodCall|StaticCall|null  $call  the call whose key list shapes a returned model
     * @return ValueExpressionResult
     */
    private function filterReturnWithoutImports(array $result, string $methodName, MethodCall|NullsafeMethodCall|StaticCall|null $call): array
    {
        if (! TsTypeString::shapeValueHasUnimportableToken($result['type'])) {
            return $result;
        }

        $models = [];

        foreach ([...(isset($result['modelFqcn']) ? [$result['modelFqcn']] : []), ...($result['embeddedModelFqcns'] ?? [])] as $model) {
            if (is_a($model, Model::class, true)) {
                $models[class_basename($model)] = $model;
            }
        }

        $keys = $call instanceof MethodCall || $call instanceof NullsafeMethodCall
            ? $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName))
            : null;
        $arms = [];

        foreach (TsTypeString::splitTopLevelUnion($result['type']) as $arm) {
            $element = Str::chopEnd($arm, '[]');

            if (isset($models[$element])) {
                $shape = $this->serializedModelShape($models[$element], $keys, $methodName === 'only');
                $arm = $element === $arm ? $shape : ValueResult::arrayWrapType($shape);
            } elseif (TsTypeString::shapeValueHasUnimportableToken($arm)) {
                return ValueResult::unknown();
            }

            $arms[] = $arm;
        }

        return [...ValueResult::unknown(), 'type' => TsTypeString::hoistNull($arms)];
    }

    /**
     * The object a model an override returns serializes to: the columns and appended accessors it writes, narrowed to
     * the call's literal keys. What `$hidden`, `$visible` or a missing append keeps out never reaches JSON, so it is
     * never named; a member naming a token is `unknown`, and runtime or empty keys leave `Record<string, unknown>`.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>|null  $keys
     */
    private function serializedModelShape(string $model, ?array $keys, bool $include): string
    {
        if ($keys === null) {
            return 'Record<string, unknown>';
        }

        $names = resolve(ModelAttributeResolver::class)->serializedAttributeNames($model);
        $picked = $include ? array_values(array_intersect(array_unique($keys), $names)) : array_values(array_diff($names, $keys));
        $shape = $picked === [] ? null : $this->literalKeyFilterResult($model, $picked, true, carriesImports: false);

        return $shape['type'] ?? 'Record<string, unknown>';
    }

    /**
     * Whether a concrete model runs Model's own declaration of a method, whose loose return only a rule narrows.
     *
     * An override declares its own return, which PHP's covariance holds every subclass to, so reflection answers it.
     *
     * @param  class-string  $class
     */
    private function runsModelMethod(string $class, string $methodName): bool
    {
        return $this->inheritsModelMethod($class, $methodName) && ! new ReflectionClass($class)->isAbstract();
    }

    /**
     * Whether a model class, abstract or not, inherits Model's own declaration of a method; `Model` itself does not.
     *
     * @param  class-string  $class
     */
    private function inheritsModelMethod(string $class, string $methodName): bool
    {
        return is_subclass_of($class, Model::class)
            && method_exists($class, $methodName)
            && new ReflectionMethod($class, $methodName)->getDeclaringClass()->getName() === Model::class;
    }

    /**
     * Type one class's method: a receiver-returning method keeps the receiver's own type, anything else reflects.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function resolveOn(string $class, string $methodName, bool $fromInside, ?CallSite $site = null): ?array
    {
        if (! method_exists($class, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($class, $methodName);

        // A non-public method called from outside goes to __call(), never to the declaration.
        if (! $fromInside && ! $method->isPublic()) {
            return null;
        }

        // A model's toArray() serializes whichever relations are loaded, runtime state no declaration describes.
        if ($methodName === 'toArray' && is_a($class, Model::class, true)) {
            return null;
        }

        $result = resolve(ReceiverClassResolver::class)->returnClasses($class, $methodName) === [$class]
            ? $this->selfType($class, $this->returnAllowsNull($method))
            : resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName, $site);

        // A vague `unknown[]` claims a list where an associative array or a keyBy() collection is a JSON object.
        // Model's own `array<string, mixed>`, such as getDirty()'s, is keyed by attribute name, so its record stands.
        $modelRecord = $result !== null && $result['type'] === 'Record<string, unknown>' && $this->runsModelMethod($class, $methodName);

        if ($result === null || (TsTypeString::isVagueTsType($result['type']) && ! $modelRecord)) {
            return null;
        }

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * The rules on one class holding a resource collection's items: toArray() and jsonSerialize() give the held value,
     * only() and except() the items they keep, first() and last() an item, and a paginator's items() and
     * getCollection() the list.
     *
     * @param  class-string  $class
     * @param  MethodCall|NullsafeMethodCall|StaticCall|null  $call  the call, whose `$default` an item rule declines
     * @return ValueExpressionResult|null
     */
    private function itemsRule(
        ReceiverType $receiver,
        string $class,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        $element = $receiver->elementResource;

        if (in_array($methodName, self::ITEMS_VALUE, true)) {
            return $this->heldItems(new ReceiverType([$class], collectedItems: true, elementResource: $element), $scope);
        }

        $name = $element === null ? 'unknown' : TsNaming::resourceTypeName($element);

        // Collection::only() and except() keep the keys they filter by, so the items they keep may come back keyed.
        if (in_array($methodName, self::ITEMS_SUBSET, true)) {
            $kept = ValueResult::collectionListType($name, false).' | '.ValueResult::collectionListType($name, true);
            $result = [...ValueResult::unknown(), 'type' => $kept];

            return $element === null ? $result : [...$result, 'resourceFqcn' => $element];
        }

        if ($element === null) {
            return null;
        }

        return match (true) {
            in_array($methodName, self::ITEMS_ELEMENT, true) => $this->mayReturnDefault($call, $methodName) ? null : [
                ...ValueResult::unknown(),
                'type' => "{$name} | null",
                'resourceFqcn' => $element,
            ],
            in_array($methodName, self::ITEMS_LIST, true) && $class !== Collection::class => [
                ...ValueResult::unknown(),
                'type' => ValueResult::collectionListType($name, ! $scope->reindexesNumericKeys),
                'resourceFqcn' => $element,
            ],
            default => null,
        };
    }

    /**
     * Whether a first() or last() call may return its `$default`, which Laravel sends when no item matches; a call
     * whose arguments cannot be read counts.
     */
    private function mayReturnDefault(MethodCall|NullsafeMethodCall|StaticCall|null $call, string $methodName): bool
    {
        if ($call === null) {
            return true;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, $methodName));

        return $arguments->hasUnpack() || $arguments->named('default') !== null;
    }

    /**
     * Whether a class holding a resource collection's items runs the method: its own, or, on a paginator, its
     * collection's, which `__call()` forwards to.
     *
     * @param  class-string  $class
     */
    private function itemsHolderRuns(string $class, string $methodName): bool
    {
        return method_exists($class, $methodName)
            || Collection::hasMacro($methodName)
            || ($class !== Collection::class && method_exists(Collection::class, $methodName));
    }

    /**
     * The TypeScript type of an instance of the receiver class itself, when it has one that can be imported.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function selfType(string $class, bool $nullable): ?array
    {
        $result = resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::toTsType($class));

        if ($result === null) {
            return null;
        }

        if ($nullable && ! in_array('null', TsTypeString::splitTopLevelUnion($result['type']), true)) {
            $result['type'] .= ' | null';
        }

        return $result;
    }

    /**
     * Whether a method's native return type, else its `@return` docblock, admits null.
     */
    private function returnAllowsNull(ReflectionMethod $method): bool
    {
        $native = $method->getReturnType();

        if ($native !== null) {
            return $native->allowsNull();
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $method->getDocComment()) ?? '';

        foreach (LaravelTsPublish::splitPhpDocUnionType($docblock) as $part) {
            $part = trim($part);

            if (strtolower($part) === 'null' || str_starts_with($part, '?')) {
                return true;
            }
        }

        return false;
    }
}
