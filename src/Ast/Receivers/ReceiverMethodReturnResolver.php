<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ProxyDeclaration;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionMethod;

/**
 * Types a method call from the PHP classes its receiver holds; the rules are in
 * docs/components/receiver-types.md § Following a method's return type.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type RuleCall MethodCall|NullsafeMethodCall|StaticCall|null
 * @phpstan-type ModelRule Closure(ReceiverType, AnalysisScope, RuleCall): (ValueExpressionResult|null)
 *
 * @internal
 */
final class ReceiverMethodReturnResolver
{
    use FiltersAttributeKeys;
    use ResolvesFilteredRelationTypes;

    /** The model methods that read one attribute by name, as a property read does. */
    public const array ATTRIBUTE_READS = ['getAttribute', 'getAttributeValue'];

    /** Model methods that return the receiver's own class or null, though Laravel declares `Model|null`. */
    private const array ROUTE_BINDINGS = ['resolveRouteBinding', 'resolveSoftDeletableRouteBinding'];

    /** What a resource collection's items answer, whichever class holds them: the held value, an item, the list. */
    private const array ITEMS_VALUE = ['toArray', 'jsonSerialize'];

    private const array ITEMS_LIST = ['items', 'getCollection'];

    private const array ITEMS_SUBSET = ['only', 'except'];

    /** The methods, lowercased as PHP matches them, that load relations onto a model or each model of a collection. */
    private const array LOADS = ['load', 'loadmissing'];

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
        $result = $this->resolveEach($receiver, $methodName, $scope, $fromInside, $call, $site, $receiver->collectedItems);

        if ($result === null) {
            return null;
        }

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
            || (is_a($class, Model::class, true)
                && $this->resolveOn(RuntimeClass::held($class), $methodName, false, null) === null);
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
     * collected list for Support\Collection, the `@tolki/types` paginator over the element for a paginator.
     *
     * @return ValueExpressionResult|null null for a receiver that holds no resource collection's items
     */
    public function heldItems(ReceiverType $receiver, AnalysisScope $scope): ?array
    {
        if (! $receiver->collectedItems) {
            return null;
        }

        $list = ResourceCollections::listValue($receiver->collectedElement, ! $scope->reindexesNumericKeys);
        $element = ResourceCollections::elementValue($receiver->collectedElement)['type'];
        $arms = [];
        $paginators = [];

        foreach ($receiver->classes as $class) {
            $paginator = TolkiTypes::MAP[$class] ?? null;

            if ($paginator === null) {
                $arms[] = $list['type'];

                continue;
            }

            $arms[] = "{$paginator}<{$element}>";
            $paginators[] = $paginator;
        }

        $result = [...$list, 'type' => implode(' | ', $arms)];

        return $paginators === [] ? $result : [...$result, 'customImports' => [TolkiTypes::MODULE => $paginators]];
    }

    /**
     * Whether a model rule types the method from the model's class, as for getKey(), toArray() or a loosely declared
     * array: every rule modelRule() holds, so one added there reaches this too.
     *
     * A handler that reads a declaration directly, as for `$this->resource->m()`, leaves such a call to these rules.
     *
     * @param  class-string  $class
     */
    public function typesModelMethod(string $class, string $methodName): bool
    {
        DependencyRecorder::recordClass($class);

        return $this->modelRule($class, $methodName) !== null;
    }

    /**
     * Whether a rule types a model method from the model's class, such as getKey(), toArray(), all() or load().
     *
     * A handler that reflects a model method directly, as VariableHandler does for a model a variable holds, leaves
     * such a call to these rules.
     *
     * @param  class-string  $class
     */
    public function typesByRule(string $class, string $methodName): bool
    {
        DependencyRecorder::recordClass($class);

        return $this->modelRule($class, $methodName) !== null || ($this->loads($methodName) && $this->runsModelMethod($class, $methodName));
    }

    /**
     * Type a method on every class the receiver holds, an API resource's undeclared one on what it forwards to, and
     * union the answers; null when any class declines, or none can run it.
     *
     * @param  bool  $armsOnly  a resource collection's items, or what an API resource forwards a call to: there a
     *                          conditional `@return` publishes only the arm a rule proves, never a guess at one
     * @return ValueExpressionResult|null
     */
    private function resolveEach(
        ReceiverType $receiver,
        string $methodName,
        AnalysisScope $scope,
        bool $fromInside,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
        ?CallSite $site,
        bool $armsOnly,
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

            $owner = $receiver->memberOwner($class, $methodName, true);
            $held = ProxyDeclaration::forwarded($owner, $methodName, true);
            $result = $this->ruleFor($receiver, $owner, $methodName, $scope, $call) ?? match (true) {
                $held !== null => $this->resolveEach($held, $methodName, $scope, false, $call, $site, true),
                $armsOnly && $this->guessesConditionalArm($owner, $methodName) => null,
                default => $this->resolveOn(
                    $scope->calleeRuntime(new ReflectionClass($owner), $receiver->runtimeInstance && $owner === $class),
                    $methodName,
                    $fromInside,
                    $site,
                    $receiver->declaredOnly,
                ),
            };

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        if ($results === []) {
            return null;
        }

        return count($results) === 1 ? $results[0] : ValueResult::unionResults($results);
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

        $picked = $methodName === 'random' ? $this->randomItems($receiver, $class, $call) : null;

        if ($picked !== null) {
            return $picked;
        }

        // first() and firstWhere() find the model the query builds, or null where no row matches.
        if ($receiver->relatedModel !== null && resolve(ReceiverClassResolver::class)->findsQueriedModel($class, $methodName)) {
            return $this->publishedModelOrNull($receiver->relatedModel);
        }

        $loaded = $this->loads($methodName) ? $this->loadedRelations($receiver, $class, $methodName, $scope, $call) : null;

        if ($loaded !== null) {
            return $loaded;
        }

        $modelRule = $this->modelRule($class, $methodName);

        if ($modelRule !== null) {
            return $modelRule($receiver, $scope, $call);
        }

        // A base collection holding the models, as toBase() hands back, has no modelKeys().
        if ($methodName === 'modelKeys' && $receiver->elementModel !== null && is_a($class, EloquentCollection::class, true)) {
            $keyType = resolve(ModelAttributeResolver::class)->keyTsType($receiver->elementModel);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => $this->listOrKeyed(ValueResult::arrayWrapType($keyType), $receiver)];
        }

        // all() hands back the models under their keys: a list, as a relation's are, unless a step broke them.
        if ($methodName === 'all' && $receiver->elementModel !== null) {
            return $this->listedModels($receiver->elementModel, $receiver->keyed);
        }

        // It writes what each model's own toArray() does, under the same keys.
        if ($methodName === 'toArray' && $receiver->elementModel !== null) {
            $element = $this->runsModelMethod($receiver->elementModel, $methodName) ? 'Record<string, unknown>' : 'unknown';

            return [...ValueResult::unknown(), 'type' => $this->listOrKeyed(ValueResult::arrayWrapType($element), $receiver)];
        }

        if (! in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return null;
        }

        // Eloquent\Collection's own filter keeps whole models by primary key, as a relation's read does.
        if ($receiver->elementModel !== null
            && ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)
            && $this->runsEloquentCollectionFilter($class, $methodName)
        ) {
            $keyed = CollectionSteps::eloquentKeyedAfter($call, $methodName, $receiver->keyed);

            return $this->listedModels($receiver->elementModel, $keyed);
        }

        if ($this->runsCollectionFilter($class, $methodName)) {
            return $this->attributeRecordResult(nullable: false);
        }

        return $this->typesAsModelFilter($class, $methodName) ? $this->attributeFilterRule($receiver, $methodName, $scope, $call) : null;
    }

    /**
     * A `load()` or `loadMissing()` that runs Laravel's own, as the model intersected with the relations it adds.
     *
     * `Post & { author: User }`, or a list of such models on an Eloquent collection; null where the call adds none this
     * types, so the model's own type stands.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function loadedRelations(
        ReceiverType $receiver,
        string $class,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        if (! $scope->carriesImports || $receiver->collectedItems || ! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall)) {
            return null;
        }

        $listed = $receiver->elementModel !== null && is_a($class, EloquentCollection::class, true);
        $model = match (true) {
            $listed => new ReflectionMethod($class, $methodName)->getDeclaringClass()->getName() === EloquentCollection::class
                ? $receiver->elementModel
                : null,
            is_a($class, Model::class, true) && $this->runsModelMethod($class, $methodName) => $class,
            default => null,
        };
        $members = $model === null ? null : resolve(LoadedRelations::class)->members($model, $call, $scope, $receiver->unsaved);

        if ($model === null || $members === null) {
            return null;
        }

        $element = class_basename($model).' & '.$members['type'];
        $result = [
            ...ValueResult::unknown(),
            'type' => $listed ? $this->listOrKeyed(ValueResult::arrayWrapType($element), $receiver) : $element,
            'modelFqcn' => $model,
            'embeddedModelFqcns' => $members['models'],
        ];

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * Whether a method name is `load()` or `loadMissing()`, in any case PHP calls it by.
     */
    private function loads(string $methodName): bool
    {
        return in_array(strtolower($methodName), self::LOADS, true);
    }

    /**
     * The rule a model's class answers a method by, whatever else the receiver holds; typesByRule() also names these.
     *
     * @param  class-string  $class
     * @return ModelRule|null
     */
    private function modelRule(string $class, string $methodName): ?Closure
    {
        // An abstract model answers with the key type it declares, which a subclass that changes it does not keep.
        if ($methodName === 'getKey' && $this->inheritsModelMethod($class, $methodName)) {
            return fn (ReceiverType $receiver): ?array => $this->columnResult(
                $receiver,
                resolve(ModelAttributeResolver::class)->keyTsType($class),
            );
        }

        // getAuthIdentifier() reads the column getAuthIdentifierName() names; an override of it is read as declared.
        if ($methodName === 'getAuthIdentifier' && is_subclass_of($class, Model::class) && method_exists($class, $methodName)) {
            return fn (ReceiverType $receiver): ?array => $this->columnResult(
                $receiver,
                resolve(ModelAttributeResolver::class)->authIdentifierTsType($class),
            );
        }

        // resolveRouteBindingQuery() queries the receiver itself, so first() holds its own class.
        if (in_array($methodName, self::ROUTE_BINDINGS, true) && $this->runsModelMethod($class, $methodName)) {
            return fn (): ?array => $this->publishedModelOrNull($class);
        }

        // Model::__get() is getAttribute(), so a literal key reads as the property does. It reads attributes through
        // getAttributeValue() and relations through getRelationValue(); an override of any of them reads as declared.
        if (in_array($methodName, self::ATTRIBUTE_READS, true)
            && $this->runsModelMethod($class, $methodName)
            && $this->runsModelMethod($class, 'getAttributeValue')
        ) {
            $relations = $methodName === 'getAttribute' && $this->runsModelMethod($class, 'getRelationValue');

            return fn (ReceiverType $receiver, AnalysisScope $scope, MethodCall|NullsafeMethodCall|StaticCall|null $call): ?array => $this->attributeRead(
                $receiver->withClasses([$class]),
                $scope,
                $call,
                $relations,
            );
        }

        // Its keys hang on the relations loaded at runtime, so only an attribute-keyed object is sure.
        if ($methodName === 'toArray' && $this->runsModelMethod($class, $methodName)) {
            return fn (): array => $this->attributeRecordResult(nullable: false);
        }

        $held = self::MODEL_ARRAY_RETURNS[$methodName] ?? null;

        return $held !== null && $this->runsModelMethod($class, $methodName)
            ? fn (): array => [...ValueResult::unknown(), 'type' => $held]
            : null;
    }

    /**
     * What `getAttribute('name')` or `getAttributeValue('name')` reads off one model: the member `->name` reads, a
     * relation only where the call loads one; null for any key but one literal string, and for a static call, which
     * `parent::` can make from an override.
     *
     * @return ValueExpressionResult|null
     */
    private function attributeRead(
        ReceiverType $model,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
        bool $relations,
    ): ?array {
        $args = $call instanceof MethodCall || $call instanceof NullsafeMethodCall ? $call->getArgs() : [];
        $key = count($args) === 1 && ! $args[0]->unpack ? $args[0]->value : null;

        return $key instanceof String_ ? ReceiverMemberReader::value($model, $key->value, $scope, $relations) : null;
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
            : [...ValueResult::unknown(), 'type' => $receiver->unsaved ? TsTypeString::withNullArm($type) : $type];
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
     * An `only()`/`except()` answer where the scope imports nothing, such as an override declaring `: static`. A
     * top-level model arm, or a list of one, is spelled as the object it serializes to; an object arm's member naming
     * any other token, such as an enum or a nested model, is `unknown`; any other arm naming one leaves `unknown`.
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
                $arm = $this->tokenMembersUnknown($arm);

                if ($arm === null) {
                    return ValueResult::unknown();
                }
            }

            $arms[] = $arm;
        }

        return [...ValueResult::unknown(), 'type' => TsTypeString::hoistNull($arms)];
    }

    /**
     * An object type with each member naming a token spelled `unknown`, a nested object's members alike, since the
     * scope cannot import it and the members beside it still say what is sent; null for a type that is no object.
     */
    private function tokenMembersUnknown(string $type): ?string
    {
        $members = TsTypeShape::members($type);

        if ($members === null || $members === []) {
            return null;
        }

        $spelled = [];

        foreach ($members as [$key, $value]) {
            $spelled[] = $key.': '.(TsTypeString::shapeValueHasUnimportableToken($value)
                ? $this->tokenMembersUnknown($value) ?? 'unknown'
                : $value);
        }

        return '{ '.implode('; ', $spelled).' }';
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
     * Type one class's method: a receiver-returning method keeps the receiver's own type, anything else reflects; a
     * method a relation or an Eloquent builder does not declare is typed on the class its query forwards it to.
     *
     * @param  RuntimeClass  $runtime  the class, known only where the object is the runtime instance of a scope that
     *                                 knows it, else assumed, since it may be a subclass
     * @param  bool  $declaredOnly  the receiver is spelled `self`, `static` or `parent`, so a method a subclass can
     *                              override is read by its declaration alone
     * @return ValueExpressionResult|null
     */
    private function resolveOn(
        RuntimeClass $runtime,
        string $methodName,
        bool $fromInside,
        ?CallSite $site,
        bool $declaredOnly = false,
    ): ?array {
        $class = $runtime->class->getName();

        if (! method_exists($class, $methodName)) {
            $forwarded = QueryForwarding::forwardedClass($class, $methodName);

            return $forwarded === null ? null : $this->resolveOn(RuntimeClass::held($forwarded), $methodName, false, $site);
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

        $result = match (true) {
            DeclaredClasses::returnClasses($class, $methodName) === [$class] => $this->selfType($class, $this->returnAllowsNull($method)),
            $declaredOnly && ReceiverType::overridable($runtime->class, $method) => resolve(MethodReturnTypeResolver::class)->declaredType($class, $methodName),
            default => resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName, $site, runtime: $runtime),
        };

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
        $element = $receiver->collectedElement;

        if (in_array($methodName, self::ITEMS_VALUE, true)) {
            return $this->heldItems(new ReceiverType([$class], collectedItems: true, collectedElement: $element), $scope);
        }

        $item = ResourceCollections::elementValue($element);
        $name = $item['type'];

        // Collection::only() and except() keep the keys they filter by, so the items they keep may come back keyed.
        if (in_array($methodName, self::ITEMS_SUBSET, true)) {
            $kept = ValueResult::collectionListType($name, false).' | '.ValueResult::collectionListType($name, true);

            return [...$item, 'type' => $kept];
        }

        if ($element === null) {
            return null;
        }

        return match (true) {
            $call !== null && CollectionSteps::elementCall($call, $methodName) => [...$item, 'type' => "{$name} | null"],
            in_array($methodName, self::ITEMS_LIST, true) && $class !== Collection::class => $this->heldItems(
                new ReceiverType([Collection::class], collectedItems: true, collectedElement: $element),
                $scope,
            ),
            default => null,
        };
    }

    /**
     * What a collection's own `random()` hands back where its item is known: the item for no `$number` or a `null` one,
     * since an empty collection throws, a list for a count or a callable, and either where the argument may be null.
     * A list of items picked with `$preserveKeys` keeps their keys, which may leave 0..n-1.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function randomItems(ReceiverType $receiver, string $class, MethodCall|NullsafeMethodCall|StaticCall|null $call): ?array
    {
        $item = $this->knownItem($receiver);
        // A paginator's __call() runs random() on its collection.
        $runs = ($receiver->collectedItems && ! method_exists($class, 'random'))
            || (method_exists($class, 'random') && in_array(
                new ReflectionMethod($class, 'random')->getDeclaringClass()->getName(),
                [Collection::class, LazyCollection::class],
                true,
            ));

        if ($item === null || $call === null || ! $runs) {
            return null;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, 'random'));

        if ($arguments->hasUnpack()) {
            return null;
        }

        $number = $arguments->named('number')?->value;
        $kept = $arguments->named('preserveKeys')?->value;
        $list = ValueResult::arrayWrapType($item['type']);
        $list = $kept === null || $this->isLiteral($kept, 'false') ? $list : ArrayArms::keyedList($list);

        $type = match (true) {
            $number === null || $this->isLiteral($number, 'null') => $item['type'],
            $number instanceof Int_ || $number instanceof ClosureExpr || $number instanceof ArrowFunction => $list,
            default => $item['type'].' | '.$list,
        };

        return [...$item, 'type' => $type];
    }

    /**
     * One item of a collection whose items are known, as its value: a resource collection's collected element, else
     * the published model a collection lists; null otherwise.
     *
     * @return ValueExpressionResult|null
     */
    private function knownItem(ReceiverType $receiver): ?array
    {
        if ($receiver->collectedItems) {
            return $receiver->collectedElement === null ? null : ResourceCollections::elementValue($receiver->collectedElement);
        }

        if ($receiver->elementModel === null) {
            return null;
        }

        $model = [...ValueResult::unknown(), 'type' => class_basename($receiver->elementModel), 'modelFqcn' => $receiver->elementModel];

        return ValueResult::namesOnlyPublishedModels($model) ? $model : null;
    }

    /**
     * Whether an argument is the constant a lowercase name spells, such as `null` or `false`.
     */
    private function isLiteral(Expr $value, string $constant): bool
    {
        return $value instanceof ConstFetch && $value->name->toLowerString() === $constant;
    }

    /**
     * Whether a method's `@return` is a conditional with an arm other than a string, of which the docblock reader keeps
     * only the first token: what that reading publishes is a guess at one arm, or a word of the condition.
     *
     * @param  class-string  $class
     */
    private function guessesConditionalArm(string $class, string $methodName): bool
    {
        if (! method_exists($class, $methodName)) {
            return false;
        }

        $read = LaravelTsPublish::extractReturnTypeFromDocblock((string) new ReflectionMethod($class, $methodName)->getDocComment());

        return $read !== null && str_starts_with($read, '(');
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

        if ($nullable) {
            $result['type'] = TsTypeString::withNullArm($result['type']);
        }

        return $result;
    }

    /**
     * A model or null, as a route binding or a query's first() returns it, where the class is a published model.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function publishedModelOrNull(string $class): ?array
    {
        $bound = $this->selfType($class, nullable: true);

        return $bound !== null && ValueResult::namesOnlyPublishedModels($bound) ? $bound : null;
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

    /**
     * A collection's models as Laravel sends them: a list, with the object arm once its keys left 0..n-1; null where
     * the model has no published file.
     *
     * @param  class-string<Model>  $model
     * @return ValueExpressionResult|null
     */
    private function listedModels(string $model, bool $keyed): ?array
    {
        $list = ValueResult::arrayWrapType(class_basename($model));
        $models = [...ValueResult::unknown(), 'type' => $keyed ? ArrayArms::keyedList($list) : $list, 'modelFqcn' => $model];

        return ValueResult::namesOnlyPublishedModels($models) ? $models : null;
    }

    /**
     * A list of a collection's items, with the object arm json_encode() writes once a step left the keys out of 0..n-1.
     */
    private function listOrKeyed(string $list, ReceiverType $receiver): string
    {
        return $receiver->keyed ? ArrayArms::keyedList($list) : $list;
    }
}
