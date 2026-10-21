<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\MacroCallResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * Answers which PHP class(es) an expression holds.
 *
 * The rules are in docs/components/receiver-types.md § Receiver resolution.
 *
 * @phpstan-type DocblockArms array{classes: list<class-string>, named: bool, nullable: bool}
 * @phpstan-type ProxyArms array{classes: non-empty-list<class-string>, named: bool, nullable: bool}
 * @phpstan-type CollectionGeneric array{key: string|null, element: string}
 *
 * @phpstan-import-type InstanceofProof from ReadsInstanceofChains
 * @phpstan-import-type VarDocBinding from AnalysisScope
 * @phpstan-import-type BindingGap from AnalysisScope
 *
 * @internal
 */
final class ReceiverClassResolver
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;
    use NarrowsInstanceofSubjects;
    use ReadsInstanceofChains;

    /** The class each value helper returns; KnownFunctionCallHandler types the helper by it too. */
    public const array HELPER_CLASSES = [
        'now' => Carbon::class,
        'today' => Carbon::class,
        'collect' => Collection::class,
    ];

    /** Marks a `new` whose model may hold attributes when read: one built for a passed copy, or one its body fills. */
    public const string HOLDS_ATTRIBUTES = 'holdsAttributes';

    /** The Model methods that write attributes, wherever a body calls them. */
    private const array ATTRIBUTE_WRITERS = ['fill', 'forceFill', 'setAttribute', 'setRawAttributes', 'offsetSet'];

    /** The Model methods whose result is a copy no row backs yet. */
    private const array UNSAVED_COPIES = ['replicate', 'replicateQuietly', 'newInstance'];

    /** The query, relation and factory methods whose model may be one no row backs yet. */
    private const array UNSAVED_QUERY_MODELS = [
        'make',
        'makeOne',
        'newModelInstance',
        'firstOrNew',
        'getModel',
        'newModel',
        'newRelatedInstanceFor',
    ];

    /** The relation methods listing models no row backs yet; an element carries no unsaved mark, so none is named. */
    private const array UNSAVED_QUERY_LISTS = ['makeMany'];

    /** The factory methods that build one model while no count is set, though each declares a collection too. */
    private const array FACTORY_MODELS = ['make', 'makeOne', 'create', 'createOne'];

    /** The factory methods that set a count, so make() and create() build a collection. */
    private const array FACTORY_COUNTS = ['count', 'times', 'forEachSequence'];

    /** The Support\Collection methods that return one item, or null where none matches and no default is passed. */
    private const array COLLECTION_ELEMENTS = ['first', 'last', 'firstWhere'];

    /** The collection methods that hand back the same items, or a leading run of them, under the keys they had. */
    private const array SAME_ITEMS = [
        'load', 'loadMissing', 'loadCount', 'loadMorph', 'loadMorphCount', 'loadAggregate', 'loadSum', 'loadMax',
        'loadMin', 'loadAvg', 'loadExists', 'each', 'tap', 'collect', 'toBase', 'makeHidden', 'mergeHidden',
        'makeVisible', 'mergeVisible', 'setVisible', 'setHidden', 'append', 'setAppends', 'withoutAppends',
        'withRelationshipAutoloading', 'takeUntil', 'takeWhile',
    ];

    /** The collection methods that hand back some of the same items, under keys they may leave out of 0..n-1. */
    private const array SAME_ITEMS_KEYED = [
        'filter', 'reject', 'where', 'whereStrict', 'whereIn', 'whereInStrict', 'whereNotIn', 'whereNotInStrict',
        'whereNull', 'whereNotNull', 'whereBetween', 'whereNotBetween', 'whereInstanceOf', 'unique', 'uniqueStrict',
        'sortBy', 'sortByDesc', 'sort', 'sortDesc', 'sortKeys', 'sortKeysDesc', 'sortKeysUsing', 'keyBy', 'skip',
        'skipUntil', 'skipWhile', 'slice', 'reverse', 'forPage', 'take', 'diff', 'intersect', 'except', 'only',
        'fresh',
    ];

    /** The collection steps that hand back other values than the items, though their docblocks may name none. */
    private const array REPLACES_ITEMS = [
        'pluck', 'map', 'mapWithKeys', 'mapToDictionary', 'mapToGroups', 'mapSpread', 'mapInto', 'flatMap', 'keys',
        'flip', 'countBy', 'zip', 'collapse', 'flatten', 'pad', 'partition', 'groupBy', 'chunk', 'chunkWhile', 'split',
        'splitIn', 'sliding', 'crossJoin', 'combine', 'transform', 'select', 'dot', 'undot',
    ];

    /** The SAME_ITEMS_KEYED steps Eloquent\Collection overrides to re-index the models it keeps, for most arguments. */
    private const array ELOQUENT_RELISTS = ['only', 'except', 'unique', 'uniqueStrict', 'diff', 'intersect'];

    /** The docblock template each query, relation and factory class binds to the model it builds. */
    private const array QUERY_MODEL_TEMPLATES = [
        Builder::class => 'TModel',
        Relation::class => 'TRelatedModel',
        Factory::class => 'TModel',
    ];

    /**
     * Mark the `new` a variable is bound to as holding attributes where its body fills the model, before the read or
     * after it: Laravel then sends what was written. A write to a member or offset through the variable fills it, as
     * does a call to an attribute writer on it, or any method called on it as a statement.
     *
     * @param  array<Node>  $body
     */
    public static function markFilled(Expr $bound, string $name, array $body): void
    {
        if (! $bound instanceof New_ || $bound->args !== []) {
            return;
        }

        if (new NodeFinder()->findFirst($body, fn (Node $node): bool => self::fills($node, $name)) !== null) {
            $bound->setAttribute(self::HOLDS_ATTRIBUTES, true);
        }
    }

    /**
     * Resolve the classes an expression holds, or null when any part of it cannot be named.
     */
    public function resolve(Expr $expr, AnalysisScope $scope): ?ReceiverType
    {
        return match (true) {
            $expr instanceof Variable => $this->fromVariable($expr, $scope),
            $expr instanceof PropertyFetch, $expr instanceof NullsafePropertyFetch => $this->fromPropertyFetch($expr, $scope),
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall => $this->fromMethodCall($expr, $scope),
            $expr instanceof StaticCall => $this->resolveStaticClass($expr, $scope),
            $expr instanceof New_ => $this->fromNew($expr, $scope),
            $expr instanceof FuncCall => $this->fromFunctionCall($expr),
            $expr instanceof Ternary => $this->fromArms(
                [$expr->if ?? $expr->cond, $expr->else],
                $scope,
                $expr->if === null,
                $expr->if === null ? null : $this->instanceofProof($expr->cond),
            ),
            $expr instanceof Coalesce => $this->fromArms([$expr->left, $expr->right], $scope, true),
            default => null,
        };
    }

    /**
     * Resolve the classes a static call returns: `X::m()`, `self::`/`static::`/`parent::`, or `$expr::m()`; one the
     * subject's `__call()` answers reads as `$this->m()`, and one a model lacks runs on a new query of the model.
     */
    public function resolveStaticClass(StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        if (! $call->name instanceof Identifier || $call->isFirstClassCallable()) {
            return null;
        }

        $method = $call->name->toString();

        if ($this->sendsToSubjectCall($call, $scope)) {
            return $this->fromThisMethodCall($method, $scope);
        }

        $receiver = $this->resolveStaticReceiver($call, $scope);

        if ($receiver === null) {
            return null;
        }

        $fromInside = $call->class instanceof Name && $call->class->isSpecialClassName();

        return $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => $this->isCallable($class, $method, $fromInside)
                    ? $this->modelFactory($class, $method, $call) ?? $this->returned($class, $method)
                    : $this->forwardedStaticQuery($class, $method),
                $receiver->classes,
            ),
            $receiver->shortCircuits,
        );
    }

    /**
     * Whether PHP sends a static-syntax call to the subject's `__call()`, where a macro or the resource proxy answers
     * it: `static::m()`, `self::m()`, `$this::m()` or the subject's own name, for a method the subject lacks.
     */
    public function sendsToSubjectCall(StaticCall $call, AnalysisScope $scope): bool
    {
        $subject = $scope->subjectReflection;

        if (! $call->name instanceof Identifier || $subject->hasMethod($call->name->toString())) {
            return false;
        }

        $class = $call->class;
        $onSubject = $class instanceof Variable
            ? $class->name === 'this'
            : $class instanceof Name && $this->subjectClassName($class, $subject) === $subject->getName();

        return $onSubject
            && ($scope->hasResourceProxy() || resolve(MacroCallResolver::class)->runsMacro($subject, $call->name->toString()));
    }

    /**
     * Resolve the class a static call is made on, not what it returns: `X::`, `self::`/`static::`/`parent::`, `$x::`.
     */
    public function resolveStaticReceiver(StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        return $call->class instanceof Name
            ? $this->namedClass($call->class, $scope)
            : $this->resolve($call->class, $scope);
    }

    /**
     * The classes a method returns: its native class type, else its `@return` docblock; null for any arm not a class.
     *
     * `static` and `$this` name the receiver class; `self` names the class that declares the method. Visibility is the
     * caller's concern.
     *
     * @return non-empty-list<class-string>|null
     */
    public function returnClasses(string $class, string $method): ?array
    {
        DependencyRecorder::recordClass($class);

        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $declaring = $reflection->getDeclaringClass()->getName();

        if ($reflection->hasReturnType()) {
            return $this->nativeClasses($reflection->getReturnType(), $class, $declaring);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment());

        return $docblock === null
            ? null
            : $this->docblockClasses($docblock, LaravelTsPublish::methodDeclaringFileClass($reflection), $class, $declaring);
    }

    /**
     * The classes a method's own `return` statements name, read from its body without running it: the class each
     * returned object holds, or the class a returned `X::class` spells; null when any return names none.
     *
     * For a method that answers with an object or its class name alike, such as `Castable::castUsing()`.
     *
     * @return non-empty-list<class-string>|null
     */
    public function bodyReturnClasses(string $class, string $method): ?array
    {
        $context = resolve(MethodLocator::class)->locate($class, $method);

        if ($context === null) {
            return null;
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);
        $classes = [];

        foreach ($this->collectReturnExpressions($context->method->stmts ?? []) as $returned) {
            $className = $this->classNameFetch($returned);
            $held = $className === null ? $this->resolve($returned, $scope) : $this->namedClass($className, $scope);

            if ($held === null) {
                return null;
            }

            $classes = [...$classes, ...$held->classes];
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * The class a bare `$this->m()` runs on when the subject neither declares `m` nor has a macro by that name: what
     * `$this->resource` holds, since `JsonResource::__call()` forwards it there. The scope carries which subjects
     * forward, so this resolver keeps to plain PHP semantics rather than one framework class's magic.
     */
    public function forwardedThisReceiver(string $method, AnalysisScope $scope): ?ReceiverType
    {
        // `__call()` runs a macro registered under the name before it forwards anything.
        if ($scope->subjectReflection->hasMethod($method)
            || resolve(MacroCallResolver::class)->runsMacro($scope->subjectReflection, $method)
        ) {
            return null;
        }

        $held = $this->proxyHeld($scope);

        if ($held !== null && $held->collectedItems) {
            return $held;
        }

        $target = $this->proxyCallTarget($method, $scope);

        return $target === null ? null : ReceiverType::of($target);
    }

    /**
     * What `$this->resource` holds as a receiver: the classes an `instanceof` proof holds it to, else those the subject
     * redeclares it as, or the backing class AnalysisScope::proxyReceiver() names when every one of them admits it, as
     * `@var Model` admits a post; null without a resource proxy, or when a redeclared arm names no class.
     */
    public function proxyHeld(AnalysisScope $scope): ?ReceiverType
    {
        if (! $scope->hasResourceProxy()) {
            return null;
        }

        $proxy = $scope->proxyReceiver();
        $declared = $this->redeclaredProxyArms($scope->subjectReflection);
        $classes = $scope->provenProxy ?? ($declared !== null && $declared['named'] ? $declared['classes'] : null);

        // A collection's constructor always swaps in its items, so a proof or a redeclaration names only their holder.
        if ($proxy !== null && $proxy->collectedItems) {
            return $classes === null
                ? $proxy
                : new ReceiverType($classes, collectedItems: true, collectedElement: $proxy->collectedElement);
        }

        $backingNarrowsAll = $declared === null
            || $this->admittedByEvery($declared['classes'], $scope->forwardsUndeclaredMembersTo);

        return match (true) {
            $scope->provenProxy === null && $backingNarrowsAll => $proxy,
            $classes === null => null,
            default => new ReceiverType($classes),
        };
    }

    /**
     * The backing model `$this->resource` publishes as when read whole with no `instanceof` proof in force: the backing
     * model when the subject does not redeclare `$resource` or one class it names admits it; null otherwise.
     *
     * @return class-string<Model>|null
     */
    public function proxyModel(AnalysisScope $scope): ?string
    {
        if (! $scope->hasResourceProxy()) {
            return null;
        }

        $backing = $scope->forwardsUndeclaredMembersTo;
        $declared = $this->redeclaredProxyArms($scope->subjectReflection);

        return $backing !== null
            && is_a($backing, Model::class, true)
            && ($declared === null || $this->admitting($declared['classes'], $backing) !== [])
            ? $backing
            : null;
    }

    /**
     * Whether the subject's own redeclaration of `$resource` admits null, as `@var Post|null` and `@var ?Post` do.
     */
    public function redeclaresNullableProxy(AnalysisScope $scope): bool
    {
        // An `if` on `$this->resource instanceof X` in toArray() may rule null out, and where its proof ends is unread.
        if (! $scope->hasResourceProxy() || $scope->instanceOfWrappedClass !== null) {
            return false;
        }

        return $this->redeclaredProxyArms($scope->subjectReflection)['nullable'] ?? false;
    }

    /**
     * The class a call forwarded to `$this->resource` runs on: the first of proxyCallOrder() that declares the method;
     * null when none does, or for a resource collection's items, which the receiver rules type.
     *
     * @return class-string|null
     */
    public function proxyCallTarget(string $method, AnalysisScope $scope): ?string
    {
        return array_find($this->proxyCallOrder($scope), fn (string $class): bool => method_exists($class, $method));
    }

    /**
     * The model a bare `$this` is when the subject under analysis is itself a model, as in its own method or accessor.
     */
    public function modelSubject(AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        return $subject->isSubclassOf(Model::class) ? ReceiverType::of($subject->getName()) : null;
    }

    /**
     * The one published model Model::__get() gives for a name; null for a collection, a scalar or several models.
     *
     * @param  class-string<Model>  $model
     * @return class-string<Model>|null
     */
    public function singleModelHeldBy(string $model, string $name): ?string
    {
        $held = $this->modelMember($model, $name)->classes ?? [];

        return count($held) === 1 && is_a($held[0], Model::class, true) && $this->isNameable($held[0]) ? $held[0] : null;
    }

    /**
     * The classes a subject holds once its `instanceof` test passes: its own classes narrowed class by class, so a
     * supertype, interface or sibling test never widens it; the tested classes when it names none.
     *
     * @param  non-empty-list<class-string>  $tested
     * @return non-empty-list<class-string>
     */
    public function narrowedSubject(Expr $subject, array $tested, AnalysisScope $scope): array
    {
        return $this->narrowed($this->narrowable($subject, $tested, $scope), $tested)->classes;
    }

    /**
     * Whether an `instanceof` test leaves `$this->resource` holding every class its redeclaration names, the backing
     * class standing in for each it is or extends, and the redeclaration admits no null, so the test rules nothing out.
     *
     * @param  non-empty-list<class-string>  $proven
     */
    public function excludesNoNamedClass(array $proven, AnalysisScope $scope): bool
    {
        if (! $scope->hasResourceProxy() || $scope->provenProxy !== null) {
            return false;
        }

        $declared = $this->redeclaredProxyArms($scope->subjectReflection);

        // A builtin arm such as `string` names no class, so any class test rules it out.
        return $declared !== null
            && $declared['named']
            && ! $declared['nullable']
            && $proven === $this->backedArms($declared['classes'], $scope->forwardsUndeclaredMembersTo);
    }

    /**
     * Resolve a variable an inline `@var` declares: the annotated assignment replaces what it held, so its own value is
     * read. The `@var` fills in only where that names no class the engine can use, or the element an Eloquent
     * collection value leaves unnamed, with the model its collection generic lists.
     *
     * @param  VarDocBinding  $span
     */
    public function resolveDeclared(string $name, array $span, AnalysisScope $scope): ?ReceiverType
    {
        $assigned = $this->fromBoundExpression($name, $span['expr'], $scope);

        if ($assigned !== null && array_any($assigned->classes, $this->isNameable(...))) {
            $element = $assigned->elementModel === null && $assigned->classes === [EloquentCollection::class]
                ? $this->declaredElementModel($span, $assigned)
                : null;

            return $element === null
                ? $assigned
                : new ReceiverType($assigned->classes, $assigned->shortCircuits, $element, keyed: $assigned->keyed);
        }

        $declared = $this->typeOf($this->declaredClasses($span, $scope));

        if ($declared === null) {
            return $assigned;
        }

        $element = $this->declaredElementModel($span, $declared);
        $keyed = in_array($this->declaredGeneric($span, $declared)['key'] ?? null, ['string', 'array-key'], true);

        return $element === null && ! $keyed
            ? $declared
            : new ReceiverType($declared->classes, elementModel: $element, keyed: $keyed);
    }

    /**
     * Whether an Eloquent\Collection's own `only()`, `except()`, `unique()`, `diff()` or `intersect()` leaves the keys
     * out of 0..n-1: each re-indexes the models it keeps, but for `only(null)` and `except(null)`, which keep every
     * key, and a `unique()` by a key, which Support\Collection's own runs.
     */
    public function eloquentKeyedAfter(MethodCall|NullsafeMethodCall $call, string $method, bool $keyed): bool
    {
        if ($method === 'diff' || $method === 'intersect') {
            return false;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $method));
        $filters = $method === 'only' || $method === 'except';
        $passed = $arguments->named($filters ? 'keys' : 'key')?->value;
        $nothing = $passed === null || ($passed instanceof ConstFetch && $passed->name->toLowerString() === 'null');

        return match (true) {
            $arguments->hasUnpack() => true,
            $filters => $nothing && $keyed,
            default => ! $nothing,
        };
    }

    /**
     * Whether a chain of calls takes a step that hands back other values than the items, as `pluck()` or `map()` does.
     */
    public function replacesItems(Expr $value): bool
    {
        while ($value instanceof MethodCall || $value instanceof NullsafeMethodCall) {
            if ($value->name instanceof Identifier && in_array($value->name->toString(), self::REPLACES_ITEMS, true)) {
                return true;
            }

            $value = $value->var;
        }

        return false;
    }

    /**
     * Whether a query, relation or factory method declares the model it builds beside `null`, as `first()` and
     * `firstWhere()` do, on the class itself or the one its query forwards the call to.
     */
    public function findsQueriedModel(string $class, string $method): bool
    {
        $answering = $this->isCallable($class, $method, false)
            ? $class
            : resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass($class, $method);
        $template = $answering === null ? null : $this->queryTemplate($answering);

        if ($answering === null || $template === null) {
            return false;
        }

        $declared = $this->declaredReturn($answering, $method);

        return $declared !== null && $this->returnsTemplate($declared, $template, besideNull: true);
    }

    /**
     * Whether one node fills the model a variable holds: a write to a member or offset through it, a call to an
     * attribute writer on it, or a statement calling a method on it.
     */
    private static function fills(Node $node, string $name): bool
    {
        if ($node instanceof Assign || $node instanceof AssignOp || $node instanceof AssignRef
            || $node instanceof PreInc || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec
        ) {
            return ! $node->var instanceof Variable && self::rootedAt($node->var, $name);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            return $node->name instanceof Identifier
                && in_array($node->name->toString(), self::ATTRIBUTE_WRITERS, true)
                && self::rootedAt($node->var, $name);
        }

        return $node instanceof Expression
            && ($node->expr instanceof MethodCall || $node->expr instanceof NullsafeMethodCall)
            && self::rootedAt($node->expr, $name);
    }

    /**
     * Whether a chain of member reads, offsets and calls starts at a variable, the bare variable included.
     */
    private static function rootedAt(Expr $expr, string $name): bool
    {
        while ($expr instanceof PropertyFetch
            || $expr instanceof NullsafePropertyFetch
            || $expr instanceof ArrayDimFetch
            || $expr instanceof MethodCall
            || $expr instanceof NullsafeMethodCall
        ) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === $name;
    }

    /**
     * Resolve a variable through the scope's bindings, or, past the write that ended its parameter binding, through
     * what the body assigned; an unbound variable declines.
     */
    private function fromVariable(Variable $variable, AnalysisScope $scope): ?ReceiverType
    {
        $name = $variable->name;

        if (! is_string($name) || $name === 'this') {
            return null;
        }

        // First, so a narrowing outranks the localVarBindings fallback a guarded variable normally reaches
        // through; varModelBindings is the same concern for a narrowed closure param or loop variable.
        if (isset($scope->varClassBindings[$name]) && $scope->holdsBinding($variable)) {
            return new ReceiverType($scope->varClassBindings[$name]);
        }

        $guard = $scope->varGuardBindings[$name] ?? null;

        // A guard proves its class only for a read past it: an earlier return still holds whatever the variable does.
        if ($guard !== null && $variable->getStartFilePos() > $guard['after']) {
            return new ReceiverType($guard['classes']);
        }

        $span = $scope->declaredAt($variable);

        if ($span === null) {
            if ($scope->holdsBinding($variable)) {
                return $this->fromBindings($name, $scope, $variable);
            }

            $gap = $scope->gapAt($variable);

            return $gap === null ? $this->fromLocal($name, $scope) : $this->fromGap($name, $gap, $scope);
        }

        return $this->resolveDeclared($name, $span, $scope);
    }

    /**
     * The classes an inline `@var` names, read as a property's `@var` is; null when it names a type that is not a
     * loadable class.
     *
     * @param  VarDocBinding  $span
     * @return non-empty-list<class-string>|null
     */
    private function declaredClasses(array $span, AnalysisScope $scope): ?array
    {
        return $this->docblockClasses($span['type'], $span['context'], $scope->subjectReflection->getName(), $span['context']->getName());
    }

    /**
     * The one concrete model an inline `@var` lists as its collection's element, as `Collection<int, Comment>` or
     * `?Collection<Comment>` does; null for any other spelling.
     *
     * @param  VarDocBinding  $span
     * @return class-string<Model>|null
     */
    private function declaredElementModel(array $span, ReceiverType $declared): ?string
    {
        $generic = $this->declaredGeneric($span, $declared);

        if ($generic === null) {
            return null;
        }

        $element = LaravelTsPublish::resolveDocblockTypeName(
            $generic['element'],
            LaravelTsPublish::parseFileUseStatements($span['context']),
            $span['context']->getNamespaceName(),
        );

        return $this->isConcreteModel($element) ? $element : null;
    }

    /**
     * The key and element an inline `@var` spells for its one collection, as `Collection<string, Comment>` does, a
     * `null` arm aside; the key is null where the generic names only the element, and null for any other spelling.
     *
     * @param  VarDocBinding  $span
     * @return CollectionGeneric|null
     */
    private function declaredGeneric(array $span, ReceiverType $declared): ?array
    {
        $arms = array_values(array_filter(
            LaravelTsPublish::splitPhpDocUnionType($span['type']),
            fn (string $arm): bool => strtolower($arm) !== 'null',
        ));

        if (count($arms) !== 1
            || ! $declared->within([Enumerable::class])
            || preg_match('/^\??[\\\\\w]+<\s*(?:(int|array-key|string)\s*,\s*)?([\\\\\w]+)\s*>$/', $arms[0], $match) !== 1
        ) {
            return null;
        }

        return ['key' => $match[1] === '' ? null : $match[1], 'element' => $match[2]];
    }

    /**
     * Resolve a variable through its model, declared-parameter, collection, request, closure-parameter or
     * local-assignment binding; $read, where given, is the read whose position tells a collection's keys.
     */
    private function fromBindings(string $name, AnalysisScope $scope, ?Variable $read = null): ?ReceiverType
    {
        if (isset($scope->varModelBindings[$name])) {
            return ReceiverType::of($scope->varModelBindings[$name]);
        }

        if (isset($scope->varDeclaredBindings[$name])) {
            return new ReceiverType($scope->varDeclaredBindings[$name]);
        }

        if (isset($scope->varCollectionBindings[$name])) {
            return new ReceiverType(
                [EloquentCollection::class],
                elementModel: $scope->varCollectionBindings[$name]['modelFqcn'],
                keyed: $read !== null && $this->rekeyedBefore($name, $read, $scope),
            );
        }

        if (isset($scope->requestVarNames[$name])) {
            return ReceiverType::of($scope->requestVarNames[$name]);
        }

        $enum = $scope->varValueBindings[$name]['directEnumFqcn'] ?? null;

        // A value bound to one enum case, such as a parameter's default, is that enum.
        if ($enum !== null && $scope->varValueBindings[$name]['type'] === LaravelTsPublish::toTsType($enum)['type']) {
            return ReceiverType::of($enum);
        }

        $bound = $scope->closureParamExprBindings[$name] ?? null;

        return $bound === null ? $this->fromLocal($name, $scope) : $this->fromBoundExpression($name, $bound, $scope);
    }

    /**
     * Whether the body's one write of a collection binding's name, which kept its models, has moved their keys off
     * 0..n-1 before a read, as `$c = $c->keyBy('id')` does.
     */
    private function rekeyedBefore(string $name, Variable $read, AnalysisScope $scope): bool
    {
        $written = $scope->literalLocalBindings()[$name] ?? null;

        return $written !== null
            && $read->getStartFilePos() > $written->getEndFilePos()
            && $this->fromBoundExpression($name, $written, $scope)?->keyed === true;
    }

    /**
     * Resolve a variable inside a gap through the values its writes assigned, joined with the binding the name came in
     * with where that may still hold; null when one of them names no class.
     *
     * @param  BindingGap  $gap
     */
    private function fromGap(string $name, array $gap, AnalysisScope $scope): ?ReceiverType
    {
        $written = $scope->beforeGap($name, $gap, fn (): array => array_map(
            fn (Expr $value): ?ReceiverType => $this->fromBoundExpression($name, $value, $scope),
            $gap[2] ?? [],
        ));
        $receivers = $gap[3] ? [$this->fromBindings($name, $scope), ...$written] : $written;

        if ($gap[2] === null || $receivers === [] || in_array(null, $receivers, true)) {
            return null;
        }

        /** @var non-empty-list<ReceiverType> $receivers */
        return count($receivers) === 1
            ? $receivers[0]
            : new ReceiverType(array_values(array_unique(array_merge(...array_map(
                fn (ReceiverType $receiver): array => $receiver->classes,
                $receivers,
            )))));
    }

    /**
     * Resolve a variable through its body's single-write assignment, re-read so the value keeps every flag it carries.
     */
    private function fromLocal(string $name, AnalysisScope $scope): ?ReceiverType
    {
        $bound = $scope->literalLocalBindings()[$name] ?? null;

        return $bound === null ? null : $this->fromBoundExpression($name, $bound, $scope);
    }

    /**
     * Resolve the expression a variable is bound to, or null while that variable is already mid-resolution.
     */
    private function fromBoundExpression(string $name, Expr $bound, AnalysisScope $scope): ?ReceiverType
    {
        if (isset($scope->resolvingLocalVars[$name])) {
            return null;
        }

        $scope->resolvingLocalVars[$name] = true;

        try {
            return $this->resolve($bound, $scope);
        } finally {
            unset($scope->resolvingLocalVars[$name]);
        }
    }

    /**
     * Whether a class names a reading the engine can use: any but a model with no published file, such as `Model`.
     */
    private function isNameable(string $class): bool
    {
        return ! is_a($class, Model::class, true)
            || ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $class]);
    }

    /**
     * Resolve `$this->prop`, `<receiver>->prop`, or `<receiver>?->prop`.
     */
    private function fromPropertyFetch(PropertyFetch|NullsafePropertyFetch $fetch, AnalysisScope $scope): ?ReceiverType
    {
        if (! $fetch->name instanceof Identifier) {
            return null;
        }

        $name = $fetch->name->toString();

        if ($fetch->var instanceof Variable && $fetch->var->name === 'this') {
            return $this->fromThisProperty($name, $scope);
        }

        $receiver = $this->resolve($fetch->var, $scope);

        if ($receiver === null) {
            return null;
        }

        return $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->memberProperty($class, $name), $receiver->classes),
            $receiver->shortCircuits || $fetch instanceof NullsafePropertyFetch,
        );
    }

    /**
     * Resolve `$this->prop`: a subject-declared property first, because PHP reads it before any `__get()` forwarding.
     */
    private function fromThisProperty(string $name, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        if ($name === 'resource' && $scope->hasResourceProxy()) {
            return $this->proxyHeld($scope);
        }

        if (! $subject->hasProperty($name)) {
            return $this->forwardedMember($name, $scope);
        }

        $property = $subject->getProperty($name);

        if ($property->isStatic()) {
            return null;
        }

        return str_starts_with($property->getDeclaringClass()->getName(), 'Illuminate\\')
            ? null
            : $this->typeOf($this->propertyClasses($property, $subject->getName()));
    }

    /**
     * Resolve `$this->prop` the subject does not declare: a member of what `$this->resource` holds, as
     * `JsonResource::__get()` reads it, else of the scope's model.
     */
    private function forwardedMember(string $name, AnalysisScope $scope): ?ReceiverType
    {
        $held = match (true) {
            $scope->hasResourceProxy() => $this->proxyHeld($scope),
            $scope->modelClass !== null => ReceiverType::of($scope->modelClass),
            default => null,
        };

        return $held === null ? null : $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->memberProperty($class, $name), $held->classes),
            false,
        );
    }

    /**
     * Resolve `$this->m()`, `<receiver>->m()`, or `<receiver>?->m()`, marking a model copy as one no row backs yet.
     */
    private function fromMethodCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): ?ReceiverType
    {
        if (! $call->name instanceof Identifier || $call->isFirstClassCallable()) {
            return null;
        }

        $method = $call->name->toString();

        $result = $call->var instanceof Variable && $call->var->name === 'this'
            ? $this->fromThisMethodCall($method, $scope)
            : $this->fromReceiverMethodCall($call, $method, $scope);

        return $result !== null && in_array($method, self::UNSAVED_COPIES, true) && $result->models() !== []
            ? $result->asUnsaved()
            : $result;
    }

    /**
     * Resolve `<receiver>->m()` or `<receiver>?->m()`.
     */
    private function fromReceiverMethodCall(MethodCall|NullsafeMethodCall $call, string $method, AnalysisScope $scope): ?ReceiverType
    {
        $receiver = $this->resolve($call->var, $scope);

        if ($receiver === null) {
            return null;
        }

        $shortCircuits = $receiver->shortCircuits || $call instanceof NullsafeMethodCall;

        if ($method === 'getRelated' && $receiver->relatedModel !== null && $receiver->within([Relation::class])) {
            return ReceiverType::of($receiver->relatedModel, $shortCircuits);
        }

        $loaded = in_array($method, ['getRelation', 'getRelationValue'], true)
            ? $this->loadedRelation($call, $receiver, $shortCircuits)
            : null;

        if ($loaded !== null) {
            return $loaded;
        }

        $authModel = $method === 'user' && $this->holdsOnlyRequests($receiver) ? resolve(AuthUserResolver::class)->model() : null;

        if ($authModel !== null) {
            return ReceiverType::of($authModel, $shortCircuits);
        }

        $element = $this->collectedElement($call, $method, $receiver);

        if ($element !== null) {
            return ReceiverType::of($element, $shortCircuits);
        }

        $kept = $this->keptItems($call, $method, $receiver, $shortCircuits);

        if ($kept !== null) {
            return $kept;
        }

        $result = $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => $this->memberMethod($class, $method, false, $receiver->relatedModel),
                $receiver->classes,
            ),
            $shortCircuits,
        );

        // A fluent call such as fill() hands the same model back, so no row backs it yet either.
        return $result !== null && $receiver->unsaved && $this->returnsItself($receiver, $method) ? $result->asUnsaved() : $result;
    }

    /**
     * Resolve `new X`, `new static` or `new self`: a model no row backs yet, which with no constructor argument
     * holds no attributes either, but for a `new` marked HOLDS_ATTRIBUTES.
     */
    private function fromNew(New_ $new, AnalysisScope $scope): ?ReceiverType
    {
        $held = $new->class instanceof Name ? $this->namedClass($new->class, $scope) : null;

        if ($held === null || $new->args !== [] || $new->getAttribute(self::HOLDS_ATTRIBUTES) === true || $held->models() === []) {
            return $held?->asUnsaved();
        }

        return $held->asAttributeless();
    }

    /**
     * Resolve `$this->m()`: the subject's own method, else that of what `$this->resource` holds, since JsonResource
     * forwards `__call()` there, else, where forwardedThisReceiver() leaves the call to a macro, what it returns.
     */
    private function fromThisMethodCall(string $method, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        if ($subject->hasMethod($method)) {
            return $this->memberMethod($subject->getName(), $method, true);
        }

        $forwarded = $this->forwardedThisReceiver($method, $scope);

        if ($forwarded === null) {
            $macro = resolve(MacroCallResolver::class)->macroFor($subject, $method);

            return $this->typeOf($this->macroReturnClasses($macro, $subject->getName()));
        }

        return $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->memberMethod($class, $method, false), $forwarded->classes),
            false,
        );
    }

    /**
     * The classes a macro returns: its native return type, else its `@return`; `static` and `self` name the subject a
     * closure is bound to.
     *
     * @return non-empty-list<class-string>|null
     */
    private function macroReturnClasses(mixed $macro, string $subjectClass): ?array
    {
        $function = resolve(MacroCallResolver::class)->reflect($macro);

        if ($function instanceof ReflectionMethod) {
            return $this->returnClasses($function->getDeclaringClass()->getName(), $function->getName());
        }

        if ($function === null) {
            return null;
        }

        if ($function->hasReturnType()) {
            return $this->nativeClasses($function->getReturnType(), $subjectClass, $subjectClass);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $function->getDocComment());
        $context = $function->getClosureScopeClass();

        return $docblock === null || $context === null
            ? null
            : $this->docblockClasses($docblock, $context, $subjectClass, $subjectClass);
    }

    /**
     * Resolve `now()`, `today()`, `collect()`, `resolve(X::class)` and `app(X::class)`.
     */
    private function fromFunctionCall(FuncCall $call): ?ReceiverType
    {
        if (! $call->name instanceof Name || $call->isFirstClassCallable()) {
            return null;
        }

        $name = $call->name->toLowerString();

        return match (true) {
            isset(self::HELPER_CLASSES[$name]) => ReceiverType::of(self::HELPER_CLASSES[$name]),
            $name === 'resolve', $name === 'app' => $this->containerClass($call),
            default => null,
        };
    }

    /**
     * Union the non-null arms of a ternary or coalesce; any unresolved non-null arm declines the whole expression.
     *
     * @param  list<Expr>  $arms
     * @param  bool  $firstArmFallsBack  a `??` or `?:` replaces a null first arm, so its short circuit never escapes
     * @param  InstanceofProof|null  $proof  what a ternary's `instanceof` condition proves about the arm that runs then
     */
    private function fromArms(array $arms, AnalysisScope $scope, bool $firstArmFallsBack, ?array $proof = null): ?ReceiverType
    {
        $types = [];
        $shortCircuits = false;

        foreach ($arms as $index => $arm) {
            if ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null') {
                continue;
            }

            $type = $proof !== null && $index === $proof[2]
                ? $this->provenArm($arm, $proof[0], $proof[1], $scope)
                : $this->resolve($arm, $scope);

            if ($type === null) {
                return null;
            }

            $types[] = $type;
            $shortCircuits = $shortCircuits || ($type->shortCircuits && ! ($firstArmFallsBack && $index === 0));
        }

        return $this->merge($types, $shortCircuits);
    }

    /**
     * The arm an `instanceof` condition proves: its tested read narrowed class by class, or a member read through that
     * subject resolved under the narrowing TernaryHandler resolves the same arm's value under.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    private function provenArm(Expr $arm, Expr $subject, array $tested, AnalysisScope $scope): ?ReceiverType
    {
        if ($this->isSameReadPath($subject, $arm)) {
            return $this->narrowed($this->narrowable($arm, $tested, $scope), $tested);
        }

        $narrowed = $this->readsThrough($arm, $subject)
            ? $this->resolveNarrowed($subject, $tested, $arm, $scope, fn (): ?ReceiverType => $this->resolve($arm, $scope))
            : null;

        return $narrowed ?? $this->resolve($arm, $scope);
    }

    /**
     * Whether an expression reads a member through the subject: a property or method chain whose receiver is its read.
     */
    private function readsThrough(Expr $expr, Expr $subject): bool
    {
        while ($expr instanceof PropertyFetch
            || $expr instanceof NullsafePropertyFetch
            || $expr instanceof MethodCall
            || $expr instanceof NullsafeMethodCall
        ) {
            $expr = $expr->var;

            if ($this->isSameReadPath($expr, $subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A true arm under its `instanceof` test: narrowed class by class, or the tested classes if it resolves to none.
     *
     * Only the classes change: the value is the one the arm resolved, so its short circuit and models still hold.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    private function narrowed(?ReceiverType $arm, array $tested): ReceiverType
    {
        if ($arm === null) {
            return new ReceiverType($tested);
        }

        $classes = [];

        foreach ($arm->classes as $class) {
            $classes = [...$classes, ...$this->narrowedClass($class, $tested)];
        }

        // No class it resolves to can pass the test, so the arm never runs and any answer holds: keep the one it had.
        return $classes === []
            ? $arm
            : $arm->withClasses(array_values(array_unique($classes)));
    }

    /**
     * What one class a true arm resolves to becomes under its tests: itself, the tested subclasses, or nothing. An
     * interface on either side of a test keeps it, since a subclass may pass that test and no one class names the pair.
     *
     * @param  class-string  $class
     * @param  non-empty-list<class-string>  $tested
     * @return list<class-string>
     */
    private function narrowedClass(string $class, array $tested): array
    {
        $subclasses = [];

        foreach ($tested as $test) {
            if (is_a($class, $test, true)) {
                return [$class];
            }

            if (is_a($test, $class, true)) {
                $subclasses[] = $test;

                continue;
            }

            if (interface_exists($class) || interface_exists($test)) {
                return [$class];
            }
        }

        return $subclasses;
    }

    /**
     * What a property holds on a receiver other than `$this`: a model's attribute or relation, or a public property.
     */
    private function memberProperty(string $class, string $name): ?ReceiverType
    {
        if (is_a($class, Model::class, true)) {
            return $this->modelMember($class, $name);
        }

        DependencyRecorder::recordClass($class);

        if (! class_exists($class) || ! property_exists($class, $name)) {
            return null;
        }

        $property = new ReflectionProperty($class, $name);

        // A non-public property read from outside goes to __get(), not to the declaration.
        return $property->isPublic() && ! $property->isStatic()
            ? $this->typeOf($this->propertyClasses($property, $class))
            : null;
    }

    /**
     * What a model member read holds: an attribute the model lists, else its relation, the order Model::__get() reads.
     *
     * @param  class-string<Model>  $model
     */
    private function modelMember(string $model, string $name): ?ReceiverType
    {
        $attribute = $this->listedAttributeName($model, $name);

        // A name only an `@property` tag types holds what a dynamic or a set relation puts there.
        if ($attribute === null) {
            return $this->relationMember($model, $name) ?? $this->typedModel($model, $name);
        }

        $attributeClass = resolve(ModelAttributeResolver::class)->resolveAttributeClass($model, $attribute);

        // The attribute hides a relation of the same name, so one holding no class names none.
        return $attributeClass === null ? $this->typedModel($model, $attribute) : ReceiverType::of($attributeClass);
    }

    /**
     * The one model a member's type names where no declaration does, as an untyped getter or an `@property` tag can.
     *
     * @param  class-string<Model>  $model
     */
    private function typedModel(string $model, string $name): ?ReceiverType
    {
        $type = resolve(ModelAttributeResolver::class)->resolveAttribute($model, $name);
        $held = $type['classFqcns'];

        // A list of the model, or a union of it with another type, holds no single model.
        return count($held) === 1 && is_a($held[0], Model::class, true) && preg_match('/^\w+( \| null)?$/', $type['type']) === 1
            ? ReceiverType::of($held[0])
            : null;
    }

    /**
     * The name the model lists the attribute Model::__get() reads under, or null when it lists none.
     *
     * @param  class-string<Model>  $model
     */
    private function listedAttributeName(string $model, string $name): ?string
    {
        $attributes = resolve(ModelAttributeResolver::class)->getAttributes($model);

        if ($attributes?->firstWhere('name', $name) !== null) {
            return $name;
        }

        // Eloquent camel-cases a key only to find an accessor method, never to read a column.
        $snake = Str::snake($name);
        $accessor = $attributes?->firstWhere('name', $snake);

        return in_array($accessor['cast'] ?? null, ['attribute', 'accessor'], true) ? $snake : null;
    }

    /**
     * What a model's `getRelation('name')` or `getRelationValue('name')` hands back: the relation under that name on
     * each model it may be called on, since the first throws for a name that is not loaded and the second loads it.
     */
    private function loadedRelation(
        MethodCall|NullsafeMethodCall $call,
        ReceiverType $receiver,
        bool $shortCircuits,
    ): ?ReceiverType {
        $name = ($call->getArgs()[0] ?? null)?->value;

        if (! $name instanceof String_) {
            return null;
        }

        return $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => is_a($class, Model::class, true)
                    ? $this->relationMember($class, $name->value)
                    : null,
                $receiver->classes,
            ),
            $shortCircuits,
        );
    }

    /**
     * What a model relation holds once loaded: its related model, a collection of them, or any of a morphTo's targets.
     *
     * @param  class-string<Model>  $model
     */
    private function relationMember(string $model, string $name): ?ReceiverType
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $relation = $resolver->resolveRelation($model, $name);

        if ($relation['morphFqcns'] !== []) {
            return new ReceiverType($relation['morphFqcns']);
        }

        if ($relation['modelFqcn'] !== null) {
            return str_ends_with($relation['type'], '[]')
                ? new ReceiverType([EloquentCollection::class], elementModel: $relation['modelFqcn'])
                : ReceiverType::of($relation['modelFqcn']);
        }

        // Only a morphTo is a known relation that names no related model.
        $isMorphTo = $resolver->getRelations($model)?->firstWhere('name', $name) !== null;

        return $isMorphTo ? ReceiverType::of($resolver->resolveMorphToBound($model, $name)) : null;
    }

    /**
     * What a method call returns on one class; a relation method on a model also remembers its related model.
     *
     * @param  bool  $fromInside  the call is on `$this`, so a non-public method is reachable
     * @param  class-string<Model>|null  $queried  the model the receiver, a query, relation or factory, builds
     */
    private function memberMethod(string $class, string $method, bool $fromInside, ?string $queried = null): ?ReceiverType
    {
        if (! $this->isCallable($class, $method, $fromInside)) {
            return $this->forwardedQueryMethod($class, $method, $queried);
        }

        $built = $queried === null ? null : $this->queriedModel($class, $method, $queried);

        if ($built !== null) {
            return $built;
        }

        $related = is_a($class, Model::class, true)
            ? resolve(ModelAttributeResolver::class)->resolveRelation($class, $method)['modelFqcn']
            : null;

        if ($related !== null) {
            return new ReceiverType($this->returnClasses($class, $method) ?? [Relation::class], relatedModel: $related);
        }

        return $this->returned($class, $method);
    }

    /**
     * What a method a relation or an Eloquent builder does not declare returns on the class its query forwards it to;
     * a relation hands itself back where its builder returns `$this`, as `forwardDecoratedCallTo()` does.
     *
     * @param  class-string<Model>|null  $queried  the model the relation or builder builds
     */
    private function forwardedQueryMethod(string $class, string $method, ?string $queried): ?ReceiverType
    {
        $forwarded = resolve(ReceiverMethodReturnResolver::class)->forwardedQueryClass($class, $method);

        if ($forwarded === Builder::class && is_a($class, Relation::class, true) && $this->declaredReturn($forwarded, $method) === '$this') {
            return new ReceiverType([$class], relatedModel: $queried);
        }

        return $forwarded === null ? null : $this->memberMethod($forwarded, $method, false, $queried);
    }

    /**
     * What a static call a concrete model does not declare returns: Model::__callStatic() runs it on a new query.
     */
    private function forwardedStaticQuery(string $class, string $method): ?ReceiverType
    {
        if (! $this->isConcreteModel($class) || method_exists($class, $method)) {
            return null;
        }

        return $this->memberMethod(Builder::class, $method, false, $class);
    }

    /**
     * What a method returns on one class, remembering the model a model's own query builds, as `query()`'s
     * `Builder<static>` does, and the model `all()`'s `Collection<int, static>` lists.
     */
    private function returned(string $class, string $method): ?ReceiverType
    {
        $classes = $this->returnClasses($class, $method);

        if ($classes === [EloquentCollection::class] && $this->isConcreteModel($class)) {
            $declared = $this->declaredReturn($class, $method);

            if ($declared !== null && $this->listsTemplate($declared, 'static')) {
                return new ReceiverType($classes, elementModel: $class);
            }
        }

        if ($classes !== [Builder::class] || ! is_a($class, Model::class, true)) {
            return $this->typeOf($classes);
        }

        $declared = $this->declaredReturn($class, $method);
        $ownQuery = $declared !== null && preg_match('/^[^|<>]+<(?:static|\$this)>$/', $declared) === 1;

        return new ReceiverType($classes, relatedModel: $ownQuery ? $class : null);
    }

    /**
     * What a query, relation or factory method returns for the model it builds: that model where the docblock returns
     * the class's model template, as Builder::make()'s `TModel` does, a list of it where the docblock lists the
     * template, as Builder::get()'s `Collection<int, TModel>` does, or the same query where it returns `$this`.
     *
     * @param  class-string<Model>  $queried
     */
    private function queriedModel(string $class, string $method, string $queried): ?ReceiverType
    {
        $template = $this->queryTemplate($class);
        $factory = is_a($class, Factory::class, true);

        if ($template === null || ($factory && in_array($method, self::FACTORY_COUNTS, true))) {
            return null;
        }

        $declared = $this->declaredReturn($class, $method);
        $list = $factory || $declared === null ? null : $this->queriedList($class, $method, $declared, $template, $queried);

        if ($list !== null) {
            return $list;
        }

        if (($declared !== null && $this->returnsTemplate($declared, $template)) || ($factory && in_array($method, self::FACTORY_MODELS, true))) {
            $model = ReceiverType::of($queried);

            return in_array($method, self::UNSAVED_QUERY_MODELS, true) ? $model->asUnsaved() : $model;
        }

        $classes = $this->returnClasses($class, $method);

        return $classes === [$class] ? new ReceiverType($classes, relatedModel: $queried) : null;
    }

    /**
     * A collection of the queried model, where a query or relation method's docblock lists the class's model template;
     * null for any other return.
     *
     * @param  class-string<Model>  $queried
     */
    private function queriedList(string $class, string $method, string $declared, string $template, string $queried): ?ReceiverType
    {
        if (in_array($method, self::UNSAVED_QUERY_LISTS, true) || ! $this->listsTemplate($declared, $template)) {
            return null;
        }

        $list = $this->typeOf($this->returnClasses($class, $method));

        return $list !== null && $list->within([Enumerable::class]) ? new ReceiverType($list->classes, elementModel: $queried) : null;
    }

    /**
     * The factory `X::factory()` returns, remembering X; none for a spread, or a count, which builds a collection.
     *
     * Asks the model itself, as newFactory(), `#[UseFactory]` or the naming convention decides the class; a factory
     * class that cannot be resolved or built declines.
     */
    private function modelFactory(string $class, string $method, StaticCall $call): ?ReceiverType
    {
        if ($method !== 'factory' || ! is_subclass_of($class, Model::class) || ! in_array(HasFactory::class, class_uses_recursive($class), true)) {
            return null;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod($class, $method));
        $count = $arguments->named('count')?->value;

        // Laravel reads an array or callable first argument as state, not a count.
        if ($arguments->hasUnpack()
            || ($count !== null && ! $count instanceof Array_ && ! $count instanceof ClosureExpr && ! $count instanceof ArrowFunction)
        ) {
            return null;
        }

        $factory = [$class, $method];

        try {
            $built = is_callable($factory) ? $factory() : null;
        } catch (Throwable) {
            return null;
        }

        if (! $built instanceof Factory) {
            return null;
        }

        DependencyRecorder::recordClass($built::class);

        return new ReceiverType([$built::class], relatedModel: $class);
    }

    /**
     * A method's `@return` docblock type as written, a trait's templates read as its consumer binds them, or null.
     */
    private function declaredReturn(string $class, string $method): ?string
    {
        $reflection = new ReflectionMethod($class, $method);
        $declared = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment());

        return $declared === null ? null : LaravelTsPublish::bindTraitTemplates($declared, $reflection);
    }

    /**
     * The docblock template a query, relation or factory class binds to the model it builds, or null for another class.
     */
    private function queryTemplate(string $class): ?string
    {
        return array_find(self::QUERY_MODEL_TEMPLATES, fn (string $name, string $queryClass): bool => is_a($class, $queryClass, true));
    }

    /**
     * Whether a docblock return is the template, alone or beside `null`, as `first()`'s `TValue|null` binds `TModel`;
     * with `$besideNull`, only beside it.
     *
     * A receiver names the model without the `null`: a `?->` read adds it, and a plain read of a missing model throws.
     */
    private function returnsTemplate(string $declared, string $template, bool $besideNull = false): bool
    {
        $parts = LaravelTsPublish::splitPhpDocUnionType($declared);
        $arms = array_map(fn (string $arm): string => ltrim(trim($arm), '?\\'), $parts);
        $null = array_any($parts, fn (string $arm): bool => str_starts_with(trim($arm), '?') || strtolower(trim($arm)) === 'null');

        return array_values(array_filter($arms, fn (string $arm): bool => strtolower($arm) !== 'null')) === [$template]
            && ($null || ! $besideNull);
    }

    /**
     * Whether a docblock return lists a template, as `Collection<int, TModel>` and `Collection<int, static>` do.
     */
    private function listsTemplate(string $declared, string $template): bool
    {
        return preg_match('/^\\\\?(?:[\\w\\\\]+\\\\)?Collection<\s*int\s*,\s*'.preg_quote($template, '/').'\s*>$/', $declared) === 1;
    }

    /**
     * The class a `self`, `static`, `parent`, or fully-qualified name refers to, from inside the subject.
     */
    private function namedClass(Name $name, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;
        $parent = $subject->getParentClass();

        // self/parent bind to the class declaring the body, and an inherited body is analyzed under the child;
        // only with no user-land ancestor is that class always the subject.
        $bodyIsSubjects = $parent === false || str_starts_with($parent->getName(), 'Illuminate\\');

        $class = match ($name->toLowerString()) {
            'static' => $subject->getName(),
            'self' => $bodyIsSubjects ? $subject->getName() : null,
            'parent' => $bodyIsSubjects && $parent !== false ? $parent->getName() : null,
            default => $name->toString(),
        };

        return $class !== null && $this->isClassLike($class) ? ReceiverType::of($class) : null;
    }

    /**
     * The class named by `resolve(X::class)` or `app(X::class)`.
     */
    private function containerClass(FuncCall $call): ?ReceiverType
    {
        $abstract = ($call->getArgs()[0] ?? null)?->value;
        $class = $abstract === null ? null : $this->classNameFetch($abstract)?->toString();

        return $class !== null && $this->isClassLike($class) ? ReceiverType::of($class) : null;
    }

    /**
     * The name an `X::class` fetch spells, or null for any other expression.
     */
    private function classNameFetch(Expr $expr): ?Name
    {
        return $expr instanceof ClassConstFetch
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
            && $expr->name->toLowerString() === 'class'
                ? $expr->class
                : null;
    }

    /**
     * A property's classes: its native class type, else its full `@var` type.
     *
     * @return non-empty-list<class-string>|null
     */
    private function propertyClasses(ReflectionProperty $property, string $receiverClass): ?array
    {
        $declaring = $property->getDeclaringClass();
        $native = $this->nativeClasses($property->getType(), $receiverClass, $declaring->getName());

        if ($native !== null) {
            return $native;
        }

        $docComment = $property->getDocComment();
        $declared = $docComment === false ? null : resolve(PropertyDocblockTypeReader::class)->extractVarType($docComment);

        return $declared === null || $declared === ''
            ? null
            : $this->docblockClasses($declared, $declaring, $receiverClass, $declaring->getName());
    }

    /**
     * The class arms of a subject's own redeclaration of `$resource`, as in `@var MediaType|string`, whether each arm
     * but `null` names a class, and whether it admits null; null for JsonResource's own `@var mixed`, or for one that
     * names no class.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ProxyArms|null
     */
    private function redeclaredProxyArms(ReflectionClass $subject): ?array
    {
        $property = $subject->getProperty('resource');
        $declaring = $property->getDeclaringClass();

        // JsonResource declares `$resource` untyped, so a subclass can only type it in a docblock.
        $docComment = str_starts_with($declaring->getName(), 'Illuminate\\') ? false : $property->getDocComment();
        $declared = $docComment === false ? null : resolve(PropertyDocblockTypeReader::class)->extractVarType($docComment);

        if ($declared === null || $declared === '') {
            return null;
        }

        $arms = $this->docblockArms($declared, $declaring, $subject->getName(), $declaring->getName());

        return $arms['classes'] === [] ? null : $arms;
    }

    /**
     * The declared classes a backing class is, or is a subtype of.
     *
     * @param  list<class-string>  $declared
     * @return list<class-string>
     */
    private function admitting(array $declared, ?string $backing): array
    {
        return $backing === null
            ? []
            : array_values(array_filter($declared, fn (string $class): bool => is_a($backing, $class, true)));
    }

    /**
     * Whether a backing class is, or is a subtype of, every declared class, so it narrows them all.
     *
     * @param  list<class-string>  $declared
     */
    private function admittedByEvery(array $declared, ?string $backing): bool
    {
        return $this->admitting($declared, $backing) === $declared;
    }

    /**
     * The declared classes with the backing class standing in for each it is or extends, as `@var Model` holds a post.
     *
     * @param  non-empty-list<class-string>  $declared
     * @return non-empty-list<class-string>
     */
    private function backedArms(array $declared, ?string $backing): array
    {
        return array_values(array_unique(array_map(
            fn (string $class): string => $backing !== null && is_a($backing, $class, true) ? $backing : $class,
            $declared,
        )));
    }

    /**
     * What an `instanceof` test on an expression narrows: on `$this->resource`, the proof already in force, else the
     * classes it is redeclared as with the backing class standing in, or the bare declaration once the test rules out
     * every one of those, as `instanceof Image` does a post; else the classes it holds.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    private function narrowable(Expr $subject, array $tested, AnalysisScope $scope): ?ReceiverType
    {
        $isProxy = $this->isProxyFetch($subject, $scope);

        if ($isProxy && $scope->provenProxy !== null) {
            return new ReceiverType($scope->provenProxy);
        }

        $declared = $isProxy ? $this->redeclaredProxyArms($scope->subjectReflection)['classes'] ?? null : null;

        if ($declared === null) {
            return $this->resolve($subject, $scope);
        }

        $backed = $this->backedArms($declared, $scope->forwardsUndeclaredMembersTo);

        return new ReceiverType(
            array_any($backed, fn (string $class): bool => $this->narrowedClass($class, $tested) !== []) ? $backed : $declared,
        );
    }

    /**
     * The classes a call forwarded to `$this->resource` may run on, in the order they answer: those an `instanceof`
     * proof holds it to, then those the subject redeclares it as, each the backing model is or extends standing for it,
     * then the guard's class and the backing class, unless the redeclaration names a class other than a model.
     *
     * @return list<class-string>
     */
    private function proxyCallOrder(AnalysisScope $scope): array
    {
        $proxy = $scope->proxyReceiver();

        if (! $scope->hasResourceProxy() || ($proxy !== null && $proxy->collectedItems)) {
            return [];
        }

        $backing = $scope->forwardsUndeclaredMembersTo;
        $declared = $this->redeclaredProxyArms($scope->subjectReflection);
        $backed = $declared === null ? [] : $this->backedArms($declared['classes'], $backing);
        $fallback = array_any($backed, fn (string $class): bool => ! is_a($class, Model::class, true))
            ? []
            : [$scope->instanceOfWrappedClass, $backing];

        return array_values(array_unique(array_filter([...$scope->provenProxy ?? [], ...$backed, ...$fallback])));
    }

    /**
     * The classes a native type names; null when it is absent, an intersection, or has any builtin arm but `null`.
     *
     * @return non-empty-list<class-string>|null
     */
    private function nativeClasses(?ReflectionType $type, string $staticClass, string $selfClass): ?array
    {
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $classes = [];

        foreach ($members as $member) {
            if (! $member instanceof ReflectionNamedType) {
                return null;
            }

            $name = $member->getName();

            if ($name === 'null') {
                continue;
            }

            $class = match ($name) {
                'static' => $staticClass,
                'self' => $selfClass,
                default => $name,
            };

            if (($member->isBuiltin() && $name !== 'static') || ! $this->isClassLike($class)) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * The classes a docblock union names, resolved against the declaring file's imports; null for any arm not a class.
     *
     * @param  ReflectionClass<object>  $context
     * @return non-empty-list<class-string>|null
     */
    private function docblockClasses(string $type, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        $arms = $this->docblockArms($type, $context, $staticClass, $selfClass);

        return $arms['named'] && $arms['classes'] !== [] ? $arms['classes'] : null;
    }

    /**
     * The class arms a docblock union names, resolved against the declaring file's imports, whether every arm but
     * `null` names one, and whether a `null` arm or a `?` prefix admits null.
     *
     * @param  ReflectionClass<object>  $context
     * @return DocblockArms
     */
    private function docblockArms(string $type, ReflectionClass $context, string $staticClass, string $selfClass): array
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($context);
        $classes = [];
        $named = true;
        $nullable = false;

        foreach (LaravelTsPublish::splitPhpDocUnionType($type) as $part) {
            // Generic arguments never change the class, but text after the closing `>`, such as `[]`, does.
            if (! preg_match('/^\??([\\\\\w$]+)(?:<.*>)?$/s', $part, $match)) {
                $named = false;

                continue;
            }

            $name = $match[1];
            $nullable = $nullable || str_starts_with($part, '?');

            if (strtolower($name) === 'null') {
                $nullable = true;

                continue;
            }

            $class = match ($name) {
                '$this', 'static' => $staticClass,
                'self' => $selfClass,
                default => LaravelTsPublish::resolveDocblockTypeName($name, $useMap, $context->getNamespaceName()),
            };

            if ($this->isClassLike($class)) {
                $classes[] = $class;
            } else {
                $named = false;
            }
        }

        return ['classes' => array_values(array_unique($classes)), 'named' => $named, 'nullable' => $nullable];
    }

    /**
     * Union resolved receivers under one short-circuit flag; any null part declines the whole union.
     *
     * An element or related model, and a resource collection's items, survive only when every part agrees on them; a
     * collection is keyed, and a model unsaved or attributeless, when any part is.
     *
     * @param  list<ReceiverType|null>  $types
     */
    private function merge(array $types, bool $shortCircuits): ?ReceiverType
    {
        $classes = [];
        $elementModels = [];
        $relatedModels = [];
        $collectedElements = [];
        $collectedItems = true;
        $unsaved = false;
        $keyed = false;
        $attributeless = false;

        foreach ($types as $type) {
            if ($type === null) {
                return null;
            }

            $classes = [...$classes, ...$type->classes];
            $elementModels[$type->elementModel ?? ''] = $type->elementModel;
            $relatedModels[$type->relatedModel ?? ''] = $type->relatedModel;
            $collectedElements[$type->collectedElement['fqcn'] ?? ''] = $type->collectedElement;
            $collectedItems = $collectedItems && $type->collectedItems;
            $unsaved = $unsaved || $type->unsaved;
            $keyed = $keyed || $type->keyed;
            $attributeless = $attributeless || $type->attributeless;
        }

        if ($classes === []) {
            return null;
        }

        return new ReceiverType(
            classes: array_values(array_unique($classes)),
            shortCircuits: $shortCircuits,
            elementModel: count($elementModels) === 1 ? reset($elementModels) : null,
            relatedModel: count($relatedModels) === 1 ? reset($relatedModels) : null,
            unsaved: $unsaved,
            collectedItems: $collectedItems,
            collectedElement: count($collectedElements) === 1 ? reset($collectedElements) : null,
            keyed: $keyed,
            attributeless: $attributeless,
        );
    }

    /**
     * Wrap a class list as a receiver, keeping null as a decline.
     *
     * @param  non-empty-list<class-string>|null  $classes
     */
    private function typeOf(?array $classes): ?ReceiverType
    {
        return $classes === null ? null : new ReceiverType($classes);
    }

    /**
     * Whether a method exists and is reachable: any visibility from inside the subject, public from outside.
     *
     * A non-public method called from outside goes to `__call()`, never to the declaration.
     */
    private function isCallable(string $class, string $method, bool $fromInside): bool
    {
        return method_exists($class, $method) && ($fromInside || new ReflectionMethod($class, $method)->isPublic());
    }

    /**
     * What a collection step that keeps the receiver's items holds: a collection of the receiver's own classes, or a
     * base one for `collect()` and `toBase()`, with its element model, keyed once a step may leave its keys out of
     * 0..n-1; null for any other step. Several of these steps inherit their docblock, which names nothing.
     */
    private function keptItems(MethodCall|NullsafeMethodCall $call, string $method, ReceiverType $receiver, bool $shortCircuits): ?ReceiverType
    {
        $keepsKeys = in_array($method, self::SAME_ITEMS, true);
        $keepsItems = $keepsKeys || $method === 'values' || in_array($method, self::SAME_ITEMS_KEYED, true);

        if (! $keepsItems
            || $receiver->collectedItems
            || ! $receiver->within([Enumerable::class])
            || ! array_all($receiver->classes, fn (string $class): bool => $this->isCallable($class, $method, false))
        ) {
            return null;
        }

        foreach ($receiver->classes as $class) {
            DependencyRecorder::recordClass($class);
        }

        return new ReceiverType(
            in_array($method, ['collect', 'toBase'], true) ? [Collection::class] : $receiver->classes,
            $shortCircuits,
            elementModel: $receiver->elementModel,
            keyed: $keepsKeys ? $receiver->keyed : $this->keyedAfter($call, $method, $receiver),
        );
    }

    /**
     * Whether a step that may move a collection's keys leaves them out of 0..n-1: `values()` re-indexes, as an
     * Eloquent\Collection's ELOQUENT_RELISTS steps do for most arguments; a front `take()` or a bare `sortKeys()`
     * keeps a list a list, while a callback, a flag or any other step may reorder or gap it.
     */
    private function keyedAfter(MethodCall|NullsafeMethodCall $call, string $method, ReceiverType $receiver): bool
    {
        return match (true) {
            $method === 'values' => false,
            $method === 'take' => $receiver->keyed || ! $this->takesFromFront($call),
            $method === 'sortKeys' => $receiver->keyed || $call->getArgs() !== [],
            in_array($method, self::ELOQUENT_RELISTS, true) && $receiver->within([EloquentCollection::class]) => $this->eloquentKeyedAfter($call, $method, $receiver->keyed),
            default => true,
        };
    }

    /**
     * Whether a `take()` call takes a literal count from the front, where a list stays a list.
     */
    private function takesFromFront(MethodCall|NullsafeMethodCall $call): bool
    {
        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, 'take'));

        return $arguments->passedCount() === 1 && ! $arguments->hasUnpack() && $arguments->named('limit')?->value instanceof Int_;
    }

    /**
     * The element model a collection's first(), last() or firstWhere() hands back, or an Eloquent collection's find()
     * of one key; none where the call passes a default, which Laravel returns where no item matches.
     *
     * @return class-string<Model>|null
     */
    private function collectedElement(MethodCall|NullsafeMethodCall $call, string $method, ReceiverType $receiver): ?string
    {
        if ($receiver->elementModel === null) {
            return null;
        }

        if ($method === 'find') {
            return $this->findsOne($call, $receiver) ? $receiver->elementModel : null;
        }

        if (! in_array($method, self::COLLECTION_ELEMENTS, true) || ! $receiver->within([Collection::class])) {
            return null;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Collection::class, $method));

        return $arguments->hasUnpack() || $arguments->named('default') !== null ? null : $receiver->elementModel;
    }

    /**
     * Whether Eloquent\Collection's own `find()` looks up one literal key with no default, so it returns a model or
     * null; a list or an Arrayable of keys returns a collection, a variable may hold either, and an override answers
     * for itself.
     */
    private function findsOne(MethodCall|NullsafeMethodCall $call, ReceiverType $receiver): bool
    {
        if (! array_all($receiver->classes, fn (string $class): bool => is_a($class, EloquentCollection::class, true)
            && new ReflectionMethod($class, 'find')->getDeclaringClass()->getName() === EloquentCollection::class)
        ) {
            return false;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'find'));
        $key = $arguments->named('key')?->value;

        return ! $arguments->hasUnpack()
            && $arguments->named('default') === null
            && ($key instanceof Int_ || $key instanceof String_);
    }

    /**
     * Whether a method returns each class the receiver holds as that same class, as `$this` or `static` does.
     */
    private function returnsItself(ReceiverType $receiver, string $method): bool
    {
        return array_all($receiver->classes, fn (string $class): bool => $this->returnClasses($class, $method) === [$class]);
    }

    /**
     * Whether every class the receiver holds is an HTTP request.
     */
    private function holdsOnlyRequests(ReceiverType $receiver): bool
    {
        foreach ($receiver->classes as $class) {
            if (! is_a($class, Request::class, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a class is a model a row can be read into: a subclass of Model that is not abstract.
     *
     * @phpstan-assert-if-true class-string<Model> $class
     */
    private function isConcreteModel(string $class): bool
    {
        return is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract();
    }

    /**
     * Whether a name is a loadable class, interface, or enum.
     *
     * @phpstan-assert-if-true class-string $name
     */
    private function isClassLike(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name);
    }
}
