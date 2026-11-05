<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\MacroCallResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\TypeAliases;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ProxyDeclaration;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Answers which PHP class(es) an expression holds.
 *
 * The rules are in docs/components/receiver-types.md § Receiver resolution.
 *
 * @phpstan-import-type InstanceofProof from ReadsInstanceofChains
 * @phpstan-import-type ProxyArms from ProxyDeclaration
 * @phpstan-import-type VarDocBinding from AnalysisScope
 * @phpstan-import-type DeclaredType from DeclaredClasses
 * @phpstan-import-type BindingGap from AnalysisScope
 * @phpstan-import-type CollectionGeneric from DeclaredClasses
 * @phpstan-import-type KeyKind from ReceiverType
 *
 * @internal
 */
final class ReceiverClassResolver
{
    use InspectsAstNodes;
    use NarrowsInstanceofSubjects;
    use ReadsInstanceofChains;
    use ResolvesAuthHelperCalls;

    /** The class each value helper returns; KnownFunctionCallHandler types the helper by it too. */
    public const array HELPER_CLASSES = [
        'now' => Carbon::class,
        'today' => Carbon::class,
        'collect' => Collection::class,
    ];

    /** Marks a `new` whose model may hold attributes when read: one built for a passed copy, or one its body fills. */
    public const string HOLDS_ATTRIBUTES = 'holdsAttributes';

    /** Lists the columns a body's writes fill on a `new` model that holds no other attribute. */
    public const string FILLED_MEMBERS = 'filledMembers';

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

    /** The docblock template each query, relation and factory class binds to the model it builds. */
    private const array QUERY_MODEL_TEMPLATES = [
        Builder::class => 'TModel',
        Relation::class => 'TRelatedModel',
        Factory::class => 'TModel',
    ];

    /** The docblock types that name no object, so a call declaring only these hands no query on. */
    private const array SCALAR_TYPES = [
        'int', 'integer', 'string', 'bool', 'boolean', 'float', 'double', 'array', 'list', 'true', 'false', 'null', 'void',
        'numeric', 'scalar', 'array-key', 'positive-int', 'negative-int', 'non-negative-int', 'non-empty-string',
        'numeric-string', 'non-empty-array', 'non-empty-list',
    ];

    /** Whether a user-defined step whose hand-back no body proves or disproves reads as handing the query back. */
    private bool $presumesSteps = false;

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
            $expr instanceof ClassConstFetch => $this->fromEnumCase($expr, $scope),
            $expr instanceof Ternary => $this->fromArms(
                [$expr->if ?? $expr->cond, $expr->else],
                $scope,
                $expr->if === null,
                $expr->if === null ? null : $this->instanceofProof($expr->cond),
            ),
            $expr instanceof Coalesce => $this->fromArms([$expr->left, $expr->right], $scope, true),
            $expr instanceof Match_ => $this->fromArms($this->valuedArms($expr), $scope, false),
            default => null,
        };
    }

    /**
     * Resolve as resolve() does, reading a user-defined query step whose hand-back is unproven as handing the query on.
     *
     * A scope, macro or custom builder method that delegates to a body the engine cannot read, or that hands the query
     * on in one branch and a value no call can follow in another, then reads as a chain rooted at a model always read.
     */
    public function resolvePresumingSteps(Expr $expr, AnalysisScope $scope): ?ReceiverType
    {
        $presumed = $this->presumesSteps;
        $this->presumesSteps = true;

        try {
            return $this->resolve($expr, $scope);
        } finally {
            $this->presumesSteps = $presumed;
        }
    }

    /**
     * The model a variable's own model binding names, while the variable reads as no narrower class: null where a
     * narrowing or a guard names a subclass, which the receiver handlers then type from. A test for a class the model
     * already is, such as `Model` or `Arrayable`, keeps it.
     *
     * @return class-string<Model>|null
     */
    public function boundModel(Variable $variable, AnalysisScope $scope): ?string
    {
        $model = is_string($variable->name) && $scope->holdsBinding($variable)
            ? $scope->varModelBindings[$variable->name] ?? null
            : null;

        return $model !== null && $this->namesNothingNarrower($this->resolve($variable, $scope), $model) ? $model : null;
    }

    /**
     * The element model a variable's own collection binding names, while the variable reads as a collection of it, or
     * as a class its Eloquent collection already is, which names no element of its own.
     *
     * @return class-string<Model>|null
     */
    public function boundElementModel(Variable $variable, AnalysisScope $scope): ?string
    {
        $element = is_string($variable->name) && $scope->holdsBinding($variable)
            ? $scope->varCollectionBindings[$variable->name]['modelFqcn'] ?? null
            : null;

        if ($element === null) {
            return null;
        }

        $held = $this->resolve($variable, $scope);

        return $held?->elementModel === $element
            || ($held?->elementModel === null && $this->namesNothingNarrower($held, EloquentCollection::class))
                ? $element
                : null;
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

        if ($this->readsSignedInUser($call)) {
            return $this->signedInUser(false);
        }

        // A named class sendsToSubjectCall() admits is the subject's own name.
        if ($this->sendsToSubjectCall($call, $scope)) {
            return $this->fromThisMethodCall($method, $scope, $scope->staticCallClass($call) ?? $scope->subjectReflection);
        }

        $receiver = $this->resolveStaticReceiver($call, $scope);

        if ($receiver === null) {
            return null;
        }

        $fromInside = $call->class instanceof Name && $call->class->isSpecialClassName();

        return $this->carried($receiver, $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => $this->isCallable($class, $method, $fromInside)
                    ? $this->modelFactory($class, $method, $call) ?? $this->forwardedReturn($call, $class, $method, $scope)
                    : $this->forwardedStaticQuery($call, $class, $method, $scope),
                $receiver->classes,
            ),
            $receiver->shortCircuits,
        ));
    }

    /**
     * Whether PHP sends a static-syntax call to the subject's `__call()`, where a macro or the resource proxy answers
     * it: `static::m()`, `self::m()`, `$this::m()` or the subject's own name, for a method the class it reaches lacks.
     */
    public function sendsToSubjectCall(StaticCall $call, AnalysisScope $scope): bool
    {
        if (! $call->name instanceof Identifier) {
            return false;
        }

        $method = $call->name->toString();
        $class = $call->class;
        $onSubject = match (true) {
            $class instanceof Variable => $class->name === 'this',
            ! $class instanceof Name => false,
            $class->isSpecialClassName() => $class->toLowerString() !== 'parent',
            default => $class->toString() === $scope->subjectReflection->getName(),
        };

        if (! $onSubject) {
            return false;
        }

        // The one named class that reaches this far is the subject's own name.
        $reached = $scope->staticCallClass($call) ?? $scope->subjectReflection;

        return ! $reached->hasMethod($method)
            && ($scope->hasResourceProxy() || resolve(MacroCallResolver::class)->runsMacro($reached, $method));
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
     * The class a bare `$this->m()` runs on when the class it reaches neither declares `m` nor has a macro by that
     * name: what `$this->resource` holds, since `JsonResource::__call()` forwards it there. The scope carries which
     * subjects forward, so this resolver keeps to plain PHP semantics rather than one framework class's magic.
     *
     * @param  ReflectionClass<object>|null  $on  the class the call reaches; null for `$this->`, which reaches
     *                                            AnalysisScope::thisMemberClass()
     */
    public function forwardedThisReceiver(string $method, AnalysisScope $scope, ?ReflectionClass $on = null): ?ReceiverType
    {
        $class = $on ?? $scope->thisMemberClass($method, true);

        // `__call()` runs a macro registered under the name before it forwards anything.
        if ($class->hasMethod($method) || resolve(MacroCallResolver::class)->runsMacro($class, $method)) {
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
     * What `$this->resource` holds as a receiver: the classes an `instanceof` proof holds it to, else what
     * ProxyDeclaration::heldOver() reads off the subject's declarations and its backing class, or a collection's items;
     * null without a resource proxy, or when a declared arm names no class.
     */
    public function proxyHeld(AnalysisScope $scope): ?ReceiverType
    {
        if (! $scope->hasResourceProxy()) {
            return null;
        }

        $proxy = $scope->proxyReceiver();

        // A collection's constructor always swaps in its items, so a proof or a redeclaration names only their holder.
        if ($proxy !== null && $proxy->collectedItems) {
            $declared = $this->redeclaredProxyArms($scope);
            $classes = $scope->provenProxy ?? ($declared !== null && $declared['named'] ? $declared['classes'] : null);

            return $classes === null
                ? $proxy
                : new ReceiverType($classes, collectedItems: true, collectedElement: $proxy->collectedElement);
        }

        if ($scope->provenProxy === null) {
            return ProxyDeclaration::heldOver($scope->proxyDeclarer(), $scope->forwardsUndeclaredMembersTo);
        }

        $arms = ProxyDeclaration::intersectionArms($scope->proxyDeclarer());
        $proven = new ReceiverType($scope->provenProxy);

        return $arms === null ? $proven : $proven->withIntersections($arms);
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
        $declared = $this->redeclaredProxyArms($scope);

        return $backing !== null
            && is_a($backing, Model::class, true)
            && ($declared === null || ProxyDeclaration::admitting($declared['classes'], $backing) !== [])
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

        return $this->redeclaredProxyArms($scope)['nullable'] ?? false;
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
     * The model a bare `$this` is when the runtime class is itself a model, as in its own method or accessor.
     */
    public function modelSubject(AnalysisScope $scope): ?ReceiverType
    {
        $runtime = $scope->runtime->class;

        return $runtime->isSubclassOf(Model::class) ? ReceiverType::of($runtime->getName()) : null;
    }

    /**
     * The one published model Model::__get() gives for a name; null for a collection, a scalar or several models.
     *
     * @param  class-string<Model>  $model
     * @return class-string<Model>|null
     */
    public function singleModelHeldBy(string $model, string $name): ?string
    {
        $held = ReceiverMemberReader::classes($model, $name)->classes ?? [];

        return count($held) === 1 && is_a($held[0], Model::class, true) && $this->isNameable($held[0]) ? $held[0] : null;
    }

    /**
     * What a subject holds once its `instanceof` test passes, never null: its own classes narrowed class by class, so a
     * supertype, interface or sibling test never widens it, and its element and keys kept; the tested classes when it
     * names none.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    public function narrowedSubject(Expr $subject, array $tested, AnalysisScope $scope): ReceiverType
    {
        return $this->narrowed($this->narrowable($subject, $tested, $scope), $tested)->withShortCircuit(false);
    }

    /**
     * The classes a value proven to hold one of $held holds once a test for $tested passes too: each narrowed class by
     * class, or the tested classes where none can pass, as a test on a variable a closure rewrote may find.
     *
     * @param  non-empty-list<class-string>  $held
     * @param  non-empty-list<class-string>  $tested
     * @return non-empty-list<class-string>
     */
    public function provenClasses(array $held, array $tested): array
    {
        return InstanceofNarrowing::proven($held, $tested);
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

        $declared = $this->redeclaredProxyArms($scope);

        // A builtin arm such as `string` names no class, so any class test rules it out.
        return $declared !== null
            && $declared['named']
            && ! $declared['nullable']
            && $proven === $this->backedArms($declared['classes'], $scope->forwardsUndeclaredMembersTo);
    }

    /**
     * Resolve a variable an inline `@var` declares: the annotated assignment replaces what it held, so its own value is
     * read. The `@var` fills in only where that names no class the engine can use, or the element an Eloquent
     * collection value leaves unnamed, with the model its collection generic lists. A guarded value reads as unread.
     *
     * An alias the `@var` names reads as its definition.
     *
     * @param  VarDocBinding  $span
     */
    public function resolveDeclared(string $name, array $span, AnalysisScope $scope): ?ReceiverType
    {
        $span = $this->expandedAlias($span);
        $assigned = $this->unguarded($this->fromBoundExpression($name, $span['expr'], $scope), $span);

        if ($assigned !== null && array_any($assigned->classes, $this->isNameable(...))) {
            $assigned = $this->keysAsDeclared($assigned, $span);
            $element = $assigned->elementModel === null && $assigned->classes === [EloquentCollection::class] && ! isset($span['relative'])
                ? $this->declaredElementModel($span, $assigned)
                : null;

            if ($element === null) {
                return $assigned;
            }

            $filled = ReceiverType::collection(
                $assigned->classes,
                $element,
                $assigned->keyed,
                $assigned->stringKeyed,
                false,
                $assigned->shortCircuits,
            );

            return $assigned->declaredOnly ? $filled->asDeclaredOnly() : $filled;
        }

        $declaredType = $this->declaredType($span, $scope);
        $declared = ReceiverType::declared($declaredType);

        if ($declaredType === null || $declared === null) {
            return $assigned;
        }

        // A `self`, `static` or `parent` names a class whose object, an element's too, may be any subclass, so members
        // read by their declarations and the tag names no element.
        if (isset($span['relative'])) {
            return $declared->asDeclaredOnly();
        }

        $element = $this->declaredElementModel($span, $declared);
        [$keyed, $stringKeyed] = ReceiverType::declaredKeys($this->declaredGeneric($span, $declared)['key'] ?? null);

        return $element === null && ! $keyed
            ? $declared
            : ReceiverType::collection($declared->classes, $element, $keyed, $stringKeyed, false)->withIntersections($declaredType);
    }

    /**
     * Whether a query, relation or factory method declares the model it builds beside `null`, as `first()` and
     * `firstWhere()` do, on the class itself or the one its query forwards the call to.
     */
    public function findsQueriedModel(string $class, string $method): bool
    {
        $answering = $this->isCallable($class, $method, false)
            ? $class
            : QueryForwarding::forwardedClass($class, $method);
        $template = $answering === null ? null : $this->queryTemplate($answering);

        if ($answering === null || $template === null) {
            return false;
        }

        $declared = $this->declaredReturn($answering, $method);

        return $declared !== null && $this->returnsTemplate($declared, $template, besideNull: true);
    }

    /**
     * Whether a value, such as the array a callback maps, is read off a guarded receiver: any object its calls, reads
     * and `collect()` wrappings hold, or those of the one write a variable sees, since `collect()` names no guard. A
     * local an inline `@var` declares reads by its declaration, so the walk stops there.
     */
    public function readsGuarded(Expr $expr, AnalysisScope $scope): bool
    {
        // The bound keeps a variable that reads its own write from walking without end.
        for ($step = 0; $step < 16; $step++) {
            $collected = CallArguments::collectArgument($expr);

            if ($collected !== null) {
                $expr = $collected;

                continue;
            }

            if ($this->resolve($expr, $scope)?->guarded === true) {
                return true;
            }

            $next = match (true) {
                $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall,
                $expr instanceof PropertyFetch, $expr instanceof NullsafePropertyFetch => $expr->var,
                $expr instanceof Variable && $scope->declaredAt($expr) === null => $scope->soleWriteSeenBy($expr),
                default => null,
            };

            if ($next === null) {
                return false;
            }

            $expr = $next;
        }

        return false;
    }

    /**
     * Resolve a variable through the scope's bindings, or, past the write that ended its parameter binding, through
     * what the body assigned, narrowed by the `instanceof` proof the read falls within; an unbound variable declines
     * unless a proof names its classes.
     */
    private function fromVariable(Variable $variable, AnalysisScope $scope): ?ReceiverType
    {
        $name = $variable->name;

        if (! is_string($name) || $name === 'this') {
            return null;
        }

        $held = $this->heldBy($variable, $name, $scope);
        $guard = $scope->guardAt($variable);

        return $guard === null ? $held : $this->guarded($held, $guard['classes']);
    }

    /**
     * What a variable holds before any `instanceof` proof.
     *
     * A morphTo parameter's targets, what an inline `@var` declares, inside a gap what its writes assigned joined with
     * the old binding where that may still hold, the one write its read sees, or the binding its name came in with.
     */
    private function heldBy(Variable $variable, string $name, AnalysisScope $scope): ?ReceiverType
    {
        // First: the targets outrank any other table a whenLoaded() parameter's name is in.
        if (isset($scope->varClassBindings[$name]) && $scope->holdsBinding($variable)) {
            return new ReceiverType($scope->varClassBindings[$name]);
        }

        $span = $scope->declaredAt($variable);

        if ($span !== null) {
            return $this->resolveDeclared($name, $span, $scope);
        }

        $gap = $scope->gapAt($variable);

        if ($gap !== null) {
            return $this->fromGap($variable, $name, $gap, $scope);
        }

        $write = $this->unreshapedWrite($name, $scope, $scope->soleWriteSeenBy($variable));

        if ($write !== null) {
            return $this->fromBoundExpression($name, $write, $scope);
        }

        return $scope->holdsBinding($variable) ? $this->fromBindings($name, $scope, $variable) : null;
    }

    /**
     * What a variable holds under an `instanceof` proof: its own value narrowed class by class, never null, so its
     * collection's element and keys still hold; the proven classes where it names none that can pass.
     *
     * @param  non-empty-list<class-string>  $proven
     */
    private function guarded(?ReceiverType $held, array $proven): ReceiverType
    {
        $classes = $held === null ? [] : InstanceofNarrowing::classes($held->classes, $proven);

        return $held === null || $classes === []
            ? new ReceiverType($proven)
            : $held->withClasses($classes)->withShortCircuit(false);
    }

    /**
     * A value an inline `@var` sits over, as the declaration met it without the guard: none for a guarded one, which
     * only the guard's reach reads, and a declared list with its keys, its element kept where the `@var` names it too.
     *
     * @param  VarDocBinding  $span
     */
    private function unguarded(?ReceiverType $assigned, array $span): ?ReceiverType
    {
        return match (true) {
            $assigned === null || $assigned->guarded => null,
            $assigned->guardsElement => $assigned->withoutElementGuard(
                $this->declaredElementModel($span, $assigned) === $assigned->elementModel,
            ),
            default => $assigned,
        };
    }

    /**
     * A value an inline `@var` sits over, read as the record a bare `string` key declares only where the `@var` spells
     * no key or that one too.
     *
     * @param  VarDocBinding  $span
     */
    private function keysAsDeclared(ReceiverType $assigned, array $span): ReceiverType
    {
        $key = $this->declaredGeneric($span, $assigned)['key'] ?? null;

        return $key === null || ReceiverType::declaredKeys($key)[1] ? $assigned : $assigned->withoutStringKeys();
    }

    /**
     * The classes an inline `@var` names, with each intersection's other members, read as a property's `@var` is; null
     * when it names a type that is not a loadable class.
     *
     * @param  VarDocBinding  $span
     * @return DeclaredType|null
     */
    private function declaredType(array $span, AnalysisScope $scope): ?array
    {
        return DeclaredClasses::docblockType($span['type'], $span['context'], $scope->runtime->class->getName(), $span['context']->getName());
    }

    /**
     * An inline `@var` read through the alias it names beside an optional `null`, as the alias's definition in the file
     * that wrote it, and through each alias that definition names in turn; any other `@var` as it is.
     *
     * @param  VarDocBinding  $span
     * @return VarDocBinding
     */
    private function expandedAlias(array $span): array
    {
        // The bound ends an alias that names itself, which TypeAliases answers as written.
        for ($step = 0; $step < 8; $step++) {
            $arms = array_values(array_filter(
                LaravelTsPublish::splitPhpDocUnionType($span['type']),
                fn (string $arm): bool => strtolower($arm) !== 'null',
            ));
            $name = count($arms) === 1 ? ltrim($arms[0], '?') : '';
            $alias = TypeAliases::isName($name) && ! DeclaredClasses::isValueClass(DocblockNames::resolve($name, $span['context']))
                ? TypeAliases::find($name, $span['context'])
                : null;

            if ($alias === null) {
                return $span;
            }

            $span = [...$span, 'type' => $alias['definition'], 'context' => new ReflectionClass($alias['class'])];
        }

        return $span;
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
        return $this->declaredGeneric($span, $declared)['element'] ?? null;
    }

    /**
     * The key and element an inline `@var` spells for its one collection, as DeclaredClasses::collectionGeneric() reads
     * it, where the value is a collection; null otherwise.
     *
     * @param  VarDocBinding  $span
     * @return CollectionGeneric|null
     */
    private function declaredGeneric(array $span, ReceiverType $declared): ?array
    {
        return $declared->within([Enumerable::class]) ? DeclaredClasses::collectionGeneric($span['type'], $span['context']) : null;
    }

    /**
     * Resolve a variable through its model, declared-parameter, collection, request, closure-parameter or
     * local-assignment binding; $read, where given, is the read whose position tells a collection's keys. A parameter
     * in relativeParameters reads by its declarations, and one in guardedParameters keeps its guard.
     */
    private function fromBindings(string $name, AnalysisScope $scope, ?Variable $read = null): ?ReceiverType
    {
        $held = $this->boundReceiver($name, $scope, $read);
        $held = match ($scope->guardedParameters[$name] ?? null) {
            'unread', 'guarded' => $held?->asGuarded(),
            'element' => $held?->asGuardingElement(),
            null => $held,
        };

        return isset($scope->relativeParameters[$name]) ? $held?->asDeclaredOnly() : $held;
    }

    /**
     * What a variable's bindings hold, before relativeParameters marks it; see fromBindings().
     */
    private function boundReceiver(string $name, AnalysisScope $scope, ?Variable $read): ?ReceiverType
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

        // A parameter binding no class of its own reads the body's one write of it, as a value read does.
        $bound = $scope->closureParamExprBindings[$name]
            ?? $this->unreshapedWrite($name, $scope, $scope->localVarBindings[$name] ?? null);

        return $bound === null ? null : $this->fromBoundExpression($name, $bound, $scope);
    }

    /**
     * Whether the body's one write of a collection binding's name, which kept its models, has moved their keys off
     * 0..n-1 before a read, as `$c = $c->keyBy('id')` does.
     */
    private function rekeyedBefore(string $name, Variable $read, AnalysisScope $scope): bool
    {
        $written = $this->unreshapedWrite($name, $scope, $scope->localVarBindings[$name] ?? null);

        return $written !== null
            && $read->getStartFilePos() > $written->getEndFilePos()
            && $this->fromBoundExpression($name, $written, $scope)?->keyed === true;
    }

    /**
     * Resolve a variable inside a gap through the values its writes assigned, a `null` aside, joined with the binding
     * the name came in with where that may still hold, or where the gap keeps it for a write naming no class; null when
     * one names none. The join is guarded where any value is.
     *
     * @param  BindingGap  $gap
     */
    private function fromGap(Variable $read, string $name, array $gap, AnalysisScope $scope): ?ReceiverType
    {
        // A member read of `null` reads null or throws, as fromArms() leaves the arm out; a gap that keeps the old
        // binding past a write naming no class keeps it for this one too.
        $values = array_values(array_filter(
            $gap[2] ?? [],
            fn (Expr $value): bool => $gap[4] || ! $this->isNullLiteral($value),
        ));
        $written = $scope->beforeGap($name, $gap, fn (): array => array_map(
            fn (Expr $value): ?ReceiverType => $this->fromWrite($name, $value, $scope),
            $values,
        ));
        $keepsOld = $gap[3] || ($gap[4] && in_array(null, $written, true));
        $written = $gap[4] ? array_values(array_filter($written)) : $written;
        $old = $keepsOld && isset($scope->varClassBindings[$name])
            ? new ReceiverType($scope->varClassBindings[$name])
            : ($keepsOld ? $this->fromBindings($name, $scope, $read) : null);
        $receivers = $keepsOld ? [$old, ...$written] : $written;

        if ($gap[2] === null || $receivers === [] || in_array(null, $receivers, true)) {
            return null;
        }

        /** @var non-empty-list<ReceiverType> $receivers */
        return count($receivers) === 1
            ? $receivers[0]
            : new ReceiverType(
                array_values(array_unique(array_merge(...array_map(
                    fn (ReceiverType $receiver): array => $receiver->classes,
                    $receivers,
                )))),
                declaredOnly: array_any($receivers, fn (ReceiverType $receiver): bool => $receiver->declaredOnly),
                guarded: array_any($receivers, fn (ReceiverType $receiver): bool => $receiver->guarded),
            );
    }

    /**
     * Resolve a value a gap's write assigned, as the inline `@var` on it declares where it is nested in a block.
     */
    private function fromWrite(string $name, Expr $value, AnalysisScope $scope): ?ReceiverType
    {
        $span = $scope->declaredWrite($name, $value);

        return $span === null
            ? $this->fromBoundExpression($name, $value, $scope)
            : $this->resolveDeclared($name, $span, $scope);
    }

    /**
     * A local's write a receiver reads, none where a by-reference call keeps the value in only some form.
     */
    private function unreshapedWrite(string $name, AnalysisScope $scope, ?Expr $write): ?Expr
    {
        return isset($scope->byReferenceForms[$name]) ? null : $write;
    }

    /**
     * Resolve the expression a variable is bound to, or null while that binding is already mid-resolution.
     */
    private function fromBoundExpression(string $name, Expr $bound, AnalysisScope $scope): ?ReceiverType
    {
        return $scope->resolvingBinding($name, $bound, fn (): ?ReceiverType => $this->resolve($bound, $scope));
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
        $proven = $scope->varGuardBindings === [] ? null : $this->provenPath($fetch, $scope);

        if ($proven !== null) {
            return $proven;
        }

        if ($fetch->var instanceof Variable && $fetch->var->name === 'this') {
            return $this->fromThisProperty($name, $scope);
        }

        $receiver = $this->resolve($fetch->var, $scope);

        if ($receiver === null) {
            return null;
        }

        return $this->carried($receiver, $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => ReceiverMemberReader::classes($receiver->memberOwner($class, $name, false), $name),
                $receiver->classes,
            ),
            $receiver->shortCircuits || $fetch instanceof NullsafePropertyFetch,
        ));
    }

    /**
     * A chain of property reads on `$this` under the `instanceof` proof in force where it sits: what it holds with that
     * proof set aside, its own classes narrowed by the proof's, or under an `if` the proof's classes guarded where it
     * names none; null where no proof holds.
     */
    private function provenPath(PropertyFetch|NullsafePropertyFetch $fetch, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $this->instanceofSubject($fetch, $scope);
        $key = $subject !== null && $subject['kind'] === 'path' ? $subject['key'] : null;
        $proof = $key === null ? null : $scope->proofAt($key, $fetch->getStartFilePos());

        if ($key === null || $proof === null) {
            return null;
        }

        // Read again without it, so the value holds what the proofs around this one leave and never re-enters it.
        $guards = $scope->varGuardBindings[$key];
        $others = array_values(array_filter($guards, fn (array $guard): bool => $guard !== $proof));

        try {
            if ($others === []) {
                unset($scope->varGuardBindings[$key]);
            } else {
                $scope->varGuardBindings[$key] = $others;
            }

            $held = $this->resolve($fetch, $scope);
        } finally {
            $scope->varGuardBindings[$key] = $guards;
        }

        // Only an `if` newly reaches a chain no class names; a ternary's arm, at no offsets, keeps its old reading.
        if ($held === null && ! AnalysisScope::isArmProof($proof)) {
            return new ReceiverType($proof['classes'], guarded: true);
        }

        return $this->narrowed($held, $proof['classes'])->withShortCircuit(false);
    }

    /**
     * Resolve `$this->prop`: a declared property first, because PHP reads it before any `__get()` forwarding, on the
     * class AnalysisScope::thisMemberClass() names.
     */
    private function fromThisProperty(string $name, AnalysisScope $scope): ?ReceiverType
    {
        if ($name === 'resource' && $scope->hasResourceProxy()) {
            return $this->proxyHeld($scope);
        }

        // Laravel's constructor sets a collection's `$collection` to a Support\Collection of its items, paged or not.
        if ($name === 'collection' && $scope->subjectReflection->isSubclassOf(ResourceCollection::class)) {
            return new ReceiverType([Collection::class], collectedItems: true, collectedElement: $scope->collectedElement, guarded: true);
        }

        $owner = $scope->thisMemberClass($name, false);

        if (! $owner->hasProperty($name)) {
            return $this->forwardedMember($name, $scope);
        }

        $property = $owner->getProperty($name);

        if ($property->isStatic()) {
            return null;
        }

        return FrameworkClasses::is(DeclaringFile::ofProperty($property, $owner)->getName())
            ? null
            : ReceiverType::declared(DeclaredClasses::propertyType($property, $owner, $scope->runtime->class->getName()));
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
            array_map(
                fn (string $class): ?ReceiverType => ReceiverMemberReader::classes($held->memberOwner($class, $name, false), $name),
                $held->classes,
            ),
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
        if ($this->readsSignedInUser($call)) {
            return $this->signedInUser($call instanceof NullsafeMethodCall);
        }

        $receiver = $this->resolve($call->var, $scope);

        return $receiver === null
            ? $this->throughConditionProxy($call, $method, $scope)
            : $this->carried($receiver, $this->receiverCallResult($call, $method, $receiver, $scope));
    }

    /**
     * `$query->when($value)->m()`: the query, where m() hands it back.
     *
     * The proxy runs m() on the query where the value holds and hands the query back where it does not.
     */
    private function throughConditionProxy(MethodCall|NullsafeMethodCall $call, string $method, AnalysisScope $scope): ?ReceiverType
    {
        $proxy = $call->var;

        if (! $proxy instanceof MethodCall
            || ! $proxy->name instanceof Identifier
            || ! in_array($proxy->name->toString(), ['when', 'unless'], true)
            || count($proxy->getArgs()) !== 1
            || $proxy->getArgs()[0]->unpack
        ) {
            return null;
        }

        $query = $this->resolve($proxy->var, $scope);

        if ($query === null || $query->relatedModel === null || count($query->classes) !== 1 || ! $query->within([Builder::class])) {
            return null;
        }

        return $this->isSameQuery($this->memberCallResult($call, $method, $query, false, $scope), $query) ? $query : null;
    }

    /**
     * What `<receiver>->m()` or `<receiver>?->m()` returns on a resolved receiver.
     */
    private function receiverCallResult(
        MethodCall|NullsafeMethodCall $call,
        string $method,
        ReceiverType $receiver,
        AnalysisScope $scope,
    ): ?ReceiverType {
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
            return $this->yielded($receiver, ReceiverType::of($element, $shortCircuits));
        }

        // An Eloquent collection's toQuery() queries the models it holds.
        if ($method === 'toQuery' && $receiver->elementModel !== null && $receiver->within([EloquentCollection::class])) {
            return $this->yielded(
                $receiver,
                new ReceiverType([QueryForwarding::builderClass($receiver->elementModel)], $shortCircuits, relatedModel: $receiver->elementModel),
            );
        }

        $kept = $this->keptItems($call, $method, $receiver, $shortCircuits) ?? $this->addedItems($call, $method, $receiver, $shortCircuits, $scope);

        return $kept ?? $this->memberCallResult($call, $method, $receiver, $shortCircuits, $scope);
    }

    /**
     * What a call returns on a resolved receiver where no relation, request or collection rule answers it.
     *
     * A query's find() of literal keys or its when()/unless(), else the method each class the receiver holds runs.
     */
    private function memberCallResult(
        MethodCall|NullsafeMethodCall|StaticCall $call,
        string $method,
        ReceiverType $receiver,
        bool $shortCircuits,
        ?AnalysisScope $scope = null,
    ): ?ReceiverType {
        $read = $this->foundByKey($call, $method, $receiver, $shortCircuits) ?? $this->conditionalQuery($call, $method, $receiver, $scope);

        if ($read !== null) {
            return $read;
        }

        $result = $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => $this->memberMethod($receiver->memberOwner($class, $method, true), $method, false, $receiver->relatedModel),
                $receiver->classes,
            ),
            $shortCircuits,
        );

        $result ??= $this->inheritedCollectionStep($method, $receiver, $shortCircuits);

        if ($this->presumesCollectionStep($method, $receiver, $result)) {
            return $receiver->keepingItems($receiver->classes, $shortCircuits, $receiver->keyed);
        }

        // A fluent call such as fill() hands the same model back, so no row backs it yet either.
        return $result !== null && $receiver->unsaved && $this->returnsItself($receiver, $method) ? $result->asUnsaved() : $result;
    }

    /**
     * The collection a step whose own docblock only inherits returns, as `Enumerable` declares it: one of the same
     * class, its items unread, as LazyCollection's `countBy()` and `pad()` hand back.
     */
    private function inheritedCollectionStep(string $method, ReceiverType $receiver, bool $shortCircuits): ?ReceiverType
    {
        if ($receiver->collectedItems || ! $receiver->within([Enumerable::class]) || ! method_exists(Enumerable::class, $method)) {
            return null;
        }

        $declared = $this->declaredReturn(Enumerable::class, $method);

        return $declared !== null && preg_match('/^static(?:<|$)/', $declared) === 1 ? new ReceiverType($receiver->classes, $shortCircuits) : null;
    }

    /**
     * Whether a collection step that loses its models' element is presumed to keep them: one the engine does not
     * follow that provably hands back no other kind of item, read as every model-rooted chain once read it.
     */
    private function presumesCollectionStep(string $method, ReceiverType $receiver, ?ReceiverType $result): bool
    {
        return $this->presumesSteps
            && $receiver->elementModel !== null
            && ! $receiver->collectedItems
            && $receiver->within([Enumerable::class])
            && ($result === null || ($result->elementModel === null && $result->within([Enumerable::class])))
            && ! CollectionSteps::changesItems($method);
    }

    /**
     * What a query's own `find()` or `findOrFail()` hands back for literal keys: its model, or a collection of it.
     *
     * Laravel picks by the argument, which the docblock spells as a conditional.
     */
    private function foundByKey(
        MethodCall|NullsafeMethodCall|StaticCall $call,
        string $method,
        ReceiverType $receiver,
        bool $shortCircuits,
    ): ?ReceiverType {
        $model = $receiver->relatedModel;

        if ($model === null
            || ! in_array($method, ['find', 'findOrFail'], true)
            || ! array_all($receiver->classes, fn (string $class): bool => $this->runsBuilderMethod($class, $method))
        ) {
            return null;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Builder::class, $method));
        $id = $arguments->hasUnpack() ? null : $arguments->named('id')?->value;

        return match (true) {
            $id instanceof Int_, $id instanceof String_ => ReceiverType::of($model, $shortCircuits),
            $id instanceof Array_ => new ReceiverType([EloquentCollection::class], $shortCircuits, elementModel: $model),
            default => null,
        };
    }

    /**
     * The query a `when()` or `unless()` on it hands back, where each callback passed hands back the one it is given.
     *
     * Conditionable returns what the callback it runs returns, or the query where that is null.
     */
    private function conditionalQuery(
        MethodCall|NullsafeMethodCall|StaticCall $call,
        string $method,
        ReceiverType $receiver,
        ?AnalysisScope $scope,
    ): ?ReceiverType {
        if (! in_array($method, ['when', 'unless'], true) || $receiver->relatedModel === null || count($receiver->classes) !== 1 || ! $receiver->within([Builder::class])) {
            return null;
        }

        $arguments = CallArguments::for($call, new ReflectionMethod(Builder::class, $method));
        $callback = $arguments->named('callback')?->value;
        $default = $arguments->named('default')?->value;

        // Without a callback when() hands back a proxy that runs the next call conditionally.
        return ! $arguments->hasUnpack()
            && $callback !== null
            && $this->isSameQuery($this->callbackQuery($callback, $receiver, $scope), $receiver)
            && ($default === null || $this->isSameQuery($this->callbackQuery($default, $receiver, $scope), $receiver))
                ? $receiver
                : null;
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

        $filled = $new->getAttribute(self::FILLED_MEMBERS);

        return $held->asAttributeless(is_array($filled) ? array_values(array_filter($filled, is_string(...))) : []);
    }

    /**
     * Resolve `$this->m()`: the method of the class the call reaches, else that of what `$this->resource` holds, since
     * JsonResource forwards `__call()` there, else, where forwardedThisReceiver() leaves the call to a macro, what it
     * returns.
     *
     * @param  ReflectionClass<object>|null  $on  the class the call reaches; null for `$this->`, which reaches
     *                                            AnalysisScope::thisMemberClass()
     */
    private function fromThisMethodCall(string $method, AnalysisScope $scope, ?ReflectionClass $on = null): ?ReceiverType
    {
        $owner = $on ?? $scope->thisMemberClass($method, true);

        if ($owner->hasMethod($method)) {
            // PHP's `static` is the runtime class even in a private method of an ancestor, so a declared one names it.
            $held = $this->memberMethod($owner->getName(), $method, true, staticClass: $scope->runtime->class->getName());

            return $held !== null && DeclaredClasses::returnsStatic($owner->getName(), $method) ? $held->asRuntimeInstance() : $held;
        }

        $forwarded = $this->forwardedThisReceiver($method, $scope, $owner);

        if ($forwarded === null) {
            $macro = resolve(MacroCallResolver::class)->macroFor($owner, $method);

            return $this->typeOf($this->macroReturnClasses($macro, $owner->getName()));
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
            return DeclaredClasses::returnClasses($function->getDeclaringClass()->getName(), $function->getName());
        }

        if ($function === null) {
            return null;
        }

        $native = DeclaredClasses::reflectedReturnType($function);

        if ($native !== null) {
            return DeclaredClasses::nativeClasses($native, $subjectClass, $subjectClass);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $function->getDocComment());
        $context = $function->getClosureScopeClass();

        return $docblock === null || $context === null
            ? null
            : DeclaredClasses::docblockClasses($docblock, $context, $subjectClass, $subjectClass);
    }

    /**
     * Resolve `E::Case`, `self::Case` or `static::Case` to its enum; a constant that is no case declines.
     */
    private function fromEnumCase(ClassConstFetch $fetch, AnalysisScope $scope): ?ReceiverType
    {
        if (! $fetch->class instanceof Name || ! $fetch->name instanceof Identifier) {
            return null;
        }

        $class = $this->scopeClassName($fetch->class, $scope);

        return enum_exists($class) && new ReflectionEnum($class)->hasCase($fetch->name->toString())
            ? ReceiverType::of($class)
            : null;
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
     * The default guard's user, for a call readsSignedInUser() admits, where no guard names another model.
     */
    private function signedInUser(bool $shortCircuits): ?ReceiverType
    {
        $model = resolve(AuthUserResolver::class)->soleModel();

        return $model === null ? null : ReceiverType::of($model, $shortCircuits);
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
            if ($this->isNullLiteral($arm)) {
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
     * The bodies of a `match`'s arms that yield a value; a `throw` arm yields none.
     *
     * @return list<Expr>
     */
    private function valuedArms(Match_ $match): array
    {
        return array_values(array_filter(
            array_map(fn (MatchArm $arm): Expr => $arm->body, $match->arms),
            fn (Expr $body): bool => ! $body instanceof Throw_,
        ));
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

        $classes = InstanceofNarrowing::classes($arm->classes, $tested);

        // No class it resolves to can pass the test, so the arm never runs and any answer holds: keep the one it had.
        return $classes === [] ? $arm : $arm->withClasses($classes);
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
                    ? ReceiverMemberReader::relationClasses($class, $name->value)
                    : null,
                $receiver->classes,
            ),
            $shortCircuits,
        );
    }

    /**
     * What a method call returns on one class; a relation method on a model also remembers its related model, and an
     * API resource's undeclared method runs on what ProxyDeclaration::forwarded() names.
     *
     * @param  bool  $fromInside  the call is on `$this`, so a non-public method is reachable
     * @param  class-string<Model>|null  $queried  the model the receiver, a query, relation or factory, builds
     * @param  class-string|null  $staticClass  the caller's runtime class, which a declared `static` names
     */
    private function memberMethod(
        string $class,
        string $method,
        bool $fromInside,
        ?string $queried = null,
        ?string $staticClass = null,
    ): ?ReceiverType {
        if (! $this->isCallable($class, $method, $fromInside)) {
            $held = ProxyDeclaration::forwarded($class, $method, true);

            return $held === null ? $this->forwardedQueryMethod($class, $method, $queried) : $this->merge(
                array_map(
                    fn (string $heldClass): ?ReceiverType => $this->memberMethod($held->memberOwner($heldClass, $method, true), $method, false),
                    $held->classes,
                ),
                false,
            )?->asGuarded();
        }

        $built = $queried === null ? null : $this->queriedModel($class, $method, $queried);

        if ($built !== null) {
            return $built;
        }

        $related = is_a($class, Model::class, true)
            ? resolve(ModelAttributeResolver::class)->resolveRelation($class, $method)['modelFqcn']
            : null;

        if ($related !== null) {
            return new ReceiverType(DeclaredClasses::returnClasses($class, $method) ?? [Relation::class], relatedModel: $related);
        }

        return $this->returned($class, $method, $staticClass);
    }

    /**
     * What a method a relation or an Eloquent builder does not declare returns on the class its query forwards it to;
     * a relation hands itself back where its builder returns `$this`, as `forwardDecoratedCallTo()` does.
     *
     * @param  class-string<Model>|null  $queried  the model the relation or builder builds
     */
    private function forwardedQueryMethod(string $class, string $method, ?string $queried): ?ReceiverType
    {
        $forwarded = QueryForwarding::forwardedClass($class, $method);

        if ($forwarded === Builder::class && is_a($class, Relation::class, true) && $this->declaredReturn($forwarded, $method) === '$this') {
            return new ReceiverType([$class], relatedModel: $queried);
        }

        if ($forwarded === null && $queried !== null && is_a($class, Builder::class, true)) {
            return $this->forwardedQuery($class, $method, $queried);
        }

        if ($forwarded === null) {
            return $queried !== null && is_a($class, Relation::class, true) && ! Relation::hasMacro($method)
                ? $this->relationForwardedQuery($class, $method, $queried)
                : null;
        }

        return $this->memberMethod($forwarded, $method, false, $queried);
    }

    /**
     * What a relation hands back for a call its builder's `__call()` answers, as `forwardDecoratedCallTo()` returns it.
     *
     * Itself where the builder hands itself back, else the query the builder hands on.
     *
     * @param  class-string<Relation<Model, Model, mixed>>  $class
     * @param  class-string<Model>  $queried
     */
    private function relationForwardedQuery(string $class, string $method, string $queried): ?ReceiverType
    {
        $builder = new ReceiverType([QueryForwarding::builderClass($queried)], relatedModel: $queried);
        $handed = $this->forwardedQuery(QueryForwarding::builderClass($queried), $method, $queried);

        return $this->isSameQuery($handed, $builder) ? new ReceiverType([$class], relatedModel: $queried) : $handed;
    }

    /**
     * What a static call Model::__callStatic() runs on a new query returns: the same call's on the model's `query()`.
     */
    private function forwardedStaticQuery(StaticCall $call, string $class, string $method, AnalysisScope $scope): ?ReceiverType
    {
        if (! QueryForwarding::queriesStatically($class, $method)) {
            return null;
        }

        $query = $this->returned($class, 'query');

        return $query === null || $query->relatedModel !== $class ? null : $this->memberCallResult($call, $method, $query, false, $scope);
    }

    /**
     * The query a base-query call, a soft-delete call, a scope or a macro Builder::__call() answers hands on.
     *
     * The first two hand the query back; a scope or macro hands on what its body returns, and one that calls itself
     * names nothing.
     *
     * @param  class-string<Builder<Model>>  $class
     * @param  class-string<Model>  $queried
     */
    private function forwardedQuery(string $class, string $method, string $queried): ?ReceiverType
    {
        $query = new ReceiverType([$class], relatedModel: $queried);

        if (QueryForwarding::forwardsToBaseQuery($class, $method, $queried) || QueryForwarding::isSoftDeleteQuery($queried, $method)) {
            return $query;
        }

        $key = 'forwarded-query:'.$class.'@'.$method.'@'.$queried.$this->modeKey();
        $memo = resolve(AnalysisMemo::class);

        return $memo->remember($key, function () use ($memo, $key, $query, $queried, $method): ?ReceiverType {
            if (! $memo->enter($key)) {
                return $this->unproven($query);
            }

            try {
                $scope = QueryForwarding::namedScope($queried, $method);

                // A local macro, as a global scope's extend() adds one, may answer a name nothing else does.
                return match (true) {
                    $scope !== null => $this->scopeQuery($scope, $query),
                    Builder::hasGlobalMacro($method) => $this->macroQuery(Builder::getGlobalMacro($method), $query),
                    default => $this->unproven($query),
                };
            } finally {
                $memo->leave($key);
            }
        });
    }

    /**
     * The query a model scope hands on: Laravel returns the scope's own return, or the query where that is null.
     */
    private function scopeQuery(ReflectionMethod $scope, ReceiverType $query): ?ReceiverType
    {
        $declared = DeclaredClasses::reflectedReturnType($scope);

        if ($declared instanceof ReflectionNamedType && $declared->getName() === 'void') {
            return $query;
        }

        $context = resolve(MethodLocator::class)->locate($scope->getDeclaringClass()->getName(), $scope->getName());
        $holder = $scope->getParameters()[0] ?? null;

        return $context === null || $holder === null
            ? $this->unproven($query)
            : $this->bodyQuery($context->method->stmts ?? [], $holder->getName(), $query, true, $context);
    }

    /**
     * The query a method a custom builder declares, untyped or as a builder it is, hands on: what its body returns.
     *
     * @param  class-string<Builder<Model>>  $class
     * @param  class-string<Model>  $queried
     */
    private function builderMethodQuery(string $class, string $method, string $queried): ?ReceiverType
    {
        if (new ReflectionMethod($class, $method)->getDeclaringClass()->getName() === Builder::class) {
            return null;
        }

        $key = 'builder-method:'.$class.'@'.$method.'@'.$queried.$this->modeKey();
        $memo = resolve(AnalysisMemo::class);

        return $memo->remember($key, function () use ($memo, $key, $class, $method, $queried): ?ReceiverType {
            $query = new ReceiverType([$class], relatedModel: $queried);
            $context = $memo->enter($key) ? resolve(MethodLocator::class)->locate($class, $method) : null;

            if ($context === null) {
                return $this->unproven($query);
            }

            try {
                return $this->bodyQuery($context->method->stmts ?? [], 'this', $query, false, $context);
            } finally {
                $memo->leave($key);
            }
        });
    }

    /**
     * The query a builder macro hands on.
     *
     * Builder::__call() binds a closure to the query and returns what it returns, and runs a method macro on its own
     * object.
     */
    private function macroQuery(mixed $macro, ReceiverType $query): ?ReceiverType
    {
        $macros = resolve(MacroCallResolver::class);
        $located = $macros->locatedClosure($macro);

        if ($located !== null) {
            [$closure, $context] = $located;
            $stmts = $closure instanceof ArrowFunction ? [new Return_($closure->expr)] : $closure->stmts;

            return $this->bodyQuery($stmts, 'this', $query, false, $context);
        }

        $function = $macros->reflect($macro);
        $context = $function instanceof ReflectionMethod
            ? resolve(MethodLocator::class)->locate($function->getDeclaringClass()->getName(), $function->getName())
            : null;

        return $context === null ? $this->unproven($query) : $this->bodyQuery($context->method->stmts ?? [], '', $query, false, $context);
    }

    /**
     * The query a when() or unless() callback, or the one write of a local holding it, hands on.
     *
     * Conditionable passes the query first and reads a null return as the query. A callback no closure spells is
     * unproven.
     */
    private function callbackQuery(Expr $callback, ReceiverType $query, ?AnalysisScope $scope): ?ReceiverType
    {
        // Conditionable calls a null callback only where the value holds, which throws, so no other value is sent.
        if ($this->isNullLiteral($callback)) {
            return $query;
        }

        $written = $callback instanceof Variable ? $scope?->soleWriteSeenBy($callback) : null;

        if ($written !== null) {
            return $this->callbackQuery($written, $query, $scope);
        }

        if (! $callback instanceof ClosureExpr && ! $callback instanceof ArrowFunction) {
            return $this->unproven($query);
        }

        $holder = $callback->params[0]->var ?? null;
        $name = $holder instanceof Variable && is_string($holder->name) ? $holder->name : '';
        $stmts = $callback instanceof ArrowFunction ? [new Return_($callback->expr)] : $callback->stmts;
        $kinds = [];

        foreach ($this->collectReturnExpressions($stmts) as $returned) {
            array_push($kinds, ...$this->returnKinds($returned, $name, $query, true, fn (): ?AnalysisScope => $scope));
        }

        return $this->joinedQuery($kinds, $query);
    }

    /**
     * The one query of one model every value a body returns hands on: the given query, or one a returned value names.
     *
     * The given query is what `$holder` holds, returned as it is, through calls that hand it back, or, where the caller
     * reads null as it, as null or no return at all; a body that may end with no return is unproven elsewhere.
     *
     * @param  array<Stmt>  $stmts
     * @param  class-string|null  $runtime  the class a static call reached the body through, which `static` names
     */
    private function bodyQuery(
        array $stmts,
        string $holder,
        ReceiverType $query,
        bool $nullHandsBack,
        MethodContext $context,
        ?string $runtime = null,
    ): ?ReceiverType {
        $bodyScope = null;
        $scope = function () use (&$bodyScope, $context, $runtime): AnalysisScope {
            return $bodyScope ??= $this->bodyScope($context, $runtime);
        };
        $kinds = $nullHandsBack || end($stmts) instanceof Return_ ? [] : [null];

        foreach ($this->collectReturnExpressions($stmts) as $returned) {
            array_push($kinds, ...$this->returnKinds($returned, $holder, $query, $nullHandsBack, $scope));
        }

        return $this->joinedQuery($kinds, $query);
    }

    /**
     * What a returned value is, arm by arm: what the resolver names, false where provably no object, null where unread.
     *
     * @param  Closure(): ?AnalysisScope  $scope  the bindings the body reads, built only where a value needs them
     * @return list<ReceiverType|false|null>
     */
    private function returnKinds(Expr $returned, string $holder, ReceiverType $query, bool $nullHandsBack, Closure $scope): array
    {
        if ($returned instanceof Ternary) {
            return [
                ...$this->returnKinds($returned->if ?? $returned->cond, $holder, $query, $nullHandsBack, $scope),
                ...$this->returnKinds($returned->else, $holder, $query, $nullHandsBack, $scope),
            ];
        }

        if ($this->handsBack($returned, $holder, $query, $nullHandsBack)) {
            return [$query];
        }

        $bodyScope = $scope();
        $held = $bodyScope === null ? null : $this->resolve($returned, $bodyScope);

        if ($held !== null) {
            return [$held->relatedModel !== null && count($held->classes) === 1 && $held->within([Builder::class])
                ? new ReceiverType($held->classes, relatedModel: $held->relatedModel)
                : $held];
        }

        return [$this->isNoQuery($returned, $holder, $query) ? false : null];
    }

    /**
     * What the values a body returns join to: the one query, model or collection every arm names.
     *
     * Where some arm is unread or provably no object, only presumed steps name what an arm names, or the given query:
     * a call after such a step throws on an arm that is no object, so only the other arm can be sent past it.
     *
     * @param  list<ReceiverType|false|null>  $kinds
     */
    private function joinedQuery(array $kinds, ReceiverType $query): ?ReceiverType
    {
        $named = array_values(array_filter($kinds, fn (ReceiverType|false|null $kind): bool => $kind instanceof ReceiverType));
        $first = $named[0] ?? null;

        if ($kinds === []) {
            return $query;
        }

        $agree = $first !== null && array_all($named, fn (ReceiverType $one): bool => $this->isSameReceiver($one, $first));

        if ($agree && count($named) === count($kinds)) {
            return $first;
        }

        // Every arm provably no object: the step hands nothing on a call can follow.
        if ($first === null && ! in_array(null, $kinds, true)) {
            return null;
        }

        // Arms naming different things keep the given query, as every model-rooted chain was once read.
        return $this->presumesSteps ? ($agree ? $first : $query) : null;
    }

    /**
     * The query an unread user-defined step hands on: none, or the given one while steps are presumed.
     */
    private function unproven(ReceiverType $query): ?ReceiverType
    {
        return $this->presumesSteps ? $query : null;
    }

    /**
     * The memo key suffix of the mode steps are read in, since a presumed answer differs from a proven one.
     */
    private function modeKey(): string
    {
        return $this->presumesSteps ? ':presumed' : '';
    }

    /**
     * Whether a returned value is provably no query.
     *
     * A literal, an operation on values, or a call on the query its holder holds that declares only types naming no
     * object, as a passthru count() does.
     */
    private function isNoQuery(Expr $returned, string $holder, ReceiverType $query): bool
    {
        if ($returned instanceof Scalar || $returned instanceof Array_ || $returned instanceof ConstFetch
            || ($returned instanceof BinaryOp && ! $returned instanceof Coalesce)
        ) {
            return true;
        }

        if (! $returned instanceof MethodCall || ! $returned->name instanceof Identifier || ! $this->handsBack($returned->var, $holder, $query)) {
            return false;
        }

        $method = $returned->name->toString();
        $class = $query->classes[0];
        $answering = $this->isCallable($class, $method, false) ? $class : QueryForwarding::forwardedClass($class, $method);

        return $answering !== null && $this->declaresNoObject($answering, $method);
    }

    /**
     * Whether a method declares a return of builtin scalar types alone, natively, tentatively or in its docblock.
     */
    private function declaresNoObject(string $class, string $method): bool
    {
        $native = DeclaredClasses::reflectedReturnType(new ReflectionMethod($class, $method));

        if ($native instanceof ReflectionNamedType && $native->getName() !== 'mixed') {
            return $native->isBuiltin() && ! in_array($native->getName(), ['object', 'static', 'self', 'iterable', 'callable'], true);
        }

        $declared = $this->declaredReturn($class, $method);

        return $declared !== null && array_all(
            LaravelTsPublish::splitPhpDocUnionType($declared),
            fn (string $arm): bool => in_array(strtolower(preg_replace('/<.*$/', '', ltrim(trim($arm), '?')) ?? ''), self::SCALAR_TYPES, true),
        );
    }

    /**
     * The bindings a body reads its returns in, `static` naming the class a call reached it through where one is given.
     *
     * @param  class-string|null  $runtime
     */
    private function bodyScope(MethodContext $context, ?string $runtime): AnalysisScope
    {
        $scope = resolve(AstEngine::class)->bindingsFor($context);

        if ($runtime !== null) {
            $scope->runtime = RuntimeClass::known(new ReflectionClass($runtime));
        }

        return $scope;
    }

    /**
     * Whether an expression is the query a variable holds, or a chain of calls on it that each hand that query back.
     */
    private function handsBack(Expr $expr, string $holder, ReceiverType $query, bool $nullHandsBack = false): bool
    {
        if ($expr instanceof Ternary) {
            return $this->handsBack($expr->if ?? $expr->cond, $holder, $query, $nullHandsBack)
                && $this->handsBack($expr->else, $holder, $query, $nullHandsBack);
        }

        if (! $expr instanceof MethodCall) {
            return ($expr instanceof Variable && $expr->name === $holder && $holder !== '') || ($nullHandsBack && $this->isNullLiteral($expr));
        }

        if (! $expr->name instanceof Identifier || $expr->isFirstClassCallable() || ! $this->handsBack($expr->var, $holder, $query)) {
            return false;
        }

        return $this->isSameQuery($this->memberCallResult($expr, $expr->name->toString(), $query, false), $query);
    }

    /**
     * Whether two receivers hold the same classes over the same model and element.
     */
    private function isSameReceiver(ReceiverType $one, ReceiverType $other): bool
    {
        return $one->classes === $other->classes && $one->relatedModel === $other->relatedModel && $one->elementModel === $other->elementModel;
    }

    /**
     * Whether a receiver is the very query another is: the same builder class over the same model.
     */
    private function isSameQuery(?ReceiverType $held, ReceiverType $query): bool
    {
        return $held !== null && $held->classes === $query->classes && $held->relatedModel === $query->relatedModel;
    }

    /**
     * Whether Eloquent\Builder's own declaration answers a method on a query or relation, called or forwarded to it.
     */
    private function runsBuilderMethod(string $class, string $method): bool
    {
        $answering = $this->isCallable($class, $method, false) ? $class : QueryForwarding::forwardedClass($class, $method);

        return $answering !== null && new ReflectionMethod($answering, $method)->getDeclaringClass()->getName() === Builder::class;
    }

    /**
     * What a static call to a method of one class returns: a call that forwards the runtime class reads a declared
     * `static` return as that class, as `static::m()` reads it; any other reads it as the class called.
     */
    private function forwardedReturn(StaticCall $call, string $class, string $method, AnalysisScope $scope): ?ReceiverType
    {
        $static = $scope->forwardsRuntimeClass($call, $class, $method) ? $scope->runtime->class->getName() : null;

        return $this->returned($class, $method, $static);
    }

    /**
     * What a method returns on one class, remembering the model a model's own query builds, as `query()`'s
     * `Builder<static>` does, and the model `all()`'s `Collection<int, static>` lists.
     *
     * @param  class-string|null  $staticClass  the caller's runtime class, which a declared `static` names
     */
    private function returned(string $class, string $method, ?string $staticClass = null): ?ReceiverType
    {
        $returned = DeclaredClasses::returnType($class, $method, $staticClass);
        $classes = $returned['classes'] ?? null;

        if ($classes === [EloquentCollection::class] && ReceiverType::isConcreteModel($class)) {
            $declared = $this->declaredReturn($class, $method);

            if ($declared !== null && $this->listsTemplate($declared, 'static')) {
                return new ReceiverType($classes, elementModel: $class);
            }

            $listed = $this->modelMethodReturn($class, $method);

            return $listed !== null && $listed->classes === $classes && $listed->elementModel !== null ? $listed : $this->typeOf($classes);
        }

        if (($classes !== null && $classes !== [Builder::class]) || ! is_a($class, Model::class, true)) {
            $listed = $classes === null ? null : $this->listedReturnModel($class, $method, $classes);

            if ($listed === null) {
                return ReceiverType::declared($returned);
            }

            [$keyed, $stringKeyed] = ReceiverType::declaredKeys($listed['key']);

            return ReceiverType::collection($classes, $listed['model'], $keyed, $stringKeyed, true);
        }

        $declared = $classes === null ? null : $this->declaredReturn($class, $method);

        if ($declared !== null && preg_match('/^[^|<>]+<(?:static|\$this)>$/', $declared) === 1) {
            return new ReceiverType([QueryForwarding::builderClass($class)], relatedModel: $class);
        }

        $read = $this->modelMethodReturn($class, $method);

        return $read !== null && ($classes === null || $read->within($classes)) ? $read : $this->typeOf($classes);
    }

    /**
     * The model a method's `@return` lists in a collection, as `EloquentCollection<int, Comment>` does, where the
     * method's value names it too: a concrete model the run publishes, with the kind of key the list spells, as
     * `Collection<string, Comment>` does; null otherwise.
     *
     * @param  non-empty-list<class-string>  $classes  the classes the method returns
     * @return array{model: class-string<Model>, key: KeyKind}|null
     */
    private function listedReturnModel(string $class, string $method, array $classes): ?array
    {
        $declared = $this->declaredReturn($class, $method);

        // Only a lone generic, with no `?` and no union arm, names the element here.
        if ($declared === null
            || ! new ReceiverType($classes)->within([Enumerable::class])
            || count(LaravelTsPublish::splitPhpDocUnionType($declared)) !== 1
            || str_starts_with($declared, '?')
        ) {
            return null;
        }

        $generic = DeclaredClasses::collectionGeneric($declared, DeclaringFile::ofMethod(new ReflectionMethod($class, $method)));
        $element = $generic['element'] ?? null;

        return $generic !== null && $element !== null
            && ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $element])
            ? ['model' => $element, 'key' => $generic['key']]
            : null;
    }

    /**
     * What a model's own method, untyped or declared as a bare `Builder` or collection, returns, read from its body.
     *
     * @param  class-string<Model>  $model
     */
    private function modelMethodReturn(string $model, string $method): ?ReceiverType
    {
        $declaring = new ReflectionMethod($model, $method)->getDeclaringClass()->getName();

        if ($declaring === Model::class || str_starts_with($declaring, 'Illuminate\\')) {
            return null;
        }

        $key = 'model-method-query:'.$model.'@'.$method.$this->modeKey();
        $memo = resolve(AnalysisMemo::class);

        return $memo->remember($key, function () use ($memo, $key, $model, $method): ?ReceiverType {
            $query = new ReceiverType([QueryForwarding::builderClass($model)], relatedModel: $model);
            $context = $memo->enter($key) ? resolve(MethodLocator::class)->locate($model, $method) : null;

            if ($context === null) {
                return $this->unproven($query);
            }

            try {
                return $this->bodyQuery($context->method->stmts ?? [], '', $query, false, $context, $model);
            } finally {
                $memo->leave($key);
            }
        });
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

        $classes = DeclaredClasses::returnClasses($class, $method);

        if ($classes === [$class]) {
            return new ReceiverType($classes, relatedModel: $queried);
        }

        // A custom builder's method declared as a builder it is, `Builder` or a parent's `self`, names no model.
        $declaresBuilder = $classes === null
            || array_all($classes, fn (string $declared): bool => is_a($declared, Builder::class, true) && is_a($class, $declared, true));

        return $declaresBuilder && is_a($class, Builder::class, true) ? $this->builderMethodQuery($class, $method, $queried) : null;
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

        $list = $this->typeOf(DeclaredClasses::returnClasses($class, $method));

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
     * Whether a docblock return lists a template, as `Collection<int, TModel>`, `LazyCollection<int, TModel>` and
     * `Collection<int, static>` do; a trait's template bound by its `@use` tag reads with a leading `\`.
     */
    private function listsTemplate(string $declared, string $template): bool
    {
        return preg_match('/^\\\\?(?:[\\w\\\\]+\\\\)?(?:Lazy)?Collection<\s*int\s*,\s*\\\\?'.preg_quote($template, '/').'\s*>$/', $declared) === 1;
    }

    /**
     * The class a `self`, `static`, `parent`, or fully-qualified name refers to in the body under read.
     */
    private function namedClass(Name $name, AnalysisScope $scope): ?ReceiverType
    {
        $class = $this->scopeClassName($name, $scope);

        return DeclaredClasses::isValueClass($class) ? ReceiverType::of($class) : null;
    }

    /**
     * The class named by `resolve(X::class)` or `app(X::class)`.
     */
    private function containerClass(FuncCall $call): ?ReceiverType
    {
        $abstract = ($call->getArgs()[0] ?? null)?->value;
        $class = $abstract === null ? null : $this->classNameFetch($abstract)?->toString();

        return $class !== null && DeclaredClasses::isValueClass($class) ? ReceiverType::of($class) : null;
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
     * Whether an expression is the `null` constant.
     */
    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
    }

    /**
     * The class arms of what a subject declares `$resource` to hold, as `@var MediaType|string` or
     * `@extends JsonResource<PostStats>` does, whether each arm but `null` names a class, and whether it admits null;
     * null for JsonResource's own `@var mixed`, for one naming no class, and for a tag naming only its backing class.
     *
     * @return ProxyArms|null
     */
    private function redeclaredProxyArms(AnalysisScope $scope): ?array
    {
        return ProxyDeclaration::armsOver($scope->proxyDeclarer(), $scope->forwardsUndeclaredMembersTo);
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

        $declared = $isProxy ? $this->redeclaredProxyArms($scope)['classes'] ?? null : null;

        if ($declared === null) {
            return $this->resolve($subject, $scope);
        }

        $backed = $this->backedArms($declared, $scope->forwardsUndeclaredMembersTo);

        return new ReceiverType(
            array_any($backed, fn (string $class): bool => InstanceofNarrowing::classes([$class], $tested) !== []) ? $backed : $declared,
        );
    }

    /**
     * The classes a call forwarded to `$this->resource` may run on, in the order they answer: an `instanceof` proof's,
     * the declared ones, the backing model standing for each it is or extends, the guard's and the backing class unless
     * a declared class is no model, then the other members of a declared intersection.
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
        $declared = $this->redeclaredProxyArms($scope);
        $backed = $declared === null ? [] : $this->backedArms($declared['classes'], $backing);
        $fallback = array_any($backed, fn (string $class): bool => ! is_a($class, Model::class, true))
            ? []
            : [$scope->instanceOfWrappedClass, $backing];
        $order = array_values(array_unique(array_filter([...$scope->provenProxy ?? [], ...$backed, ...$fallback])));
        $arms = ProxyDeclaration::intersectionArms($scope->proxyDeclarer());

        if ($order === [] || $arms === null) {
            return $order;
        }

        // A method only an intersection's other member declares runs there, so those members answer after every class.
        $intersections = new ReceiverType($order)->withIntersections($arms)->intersections;

        return array_values(array_unique([...$order, ...array_merge(...array_values($intersections))]));
    }

    /**
     * Union resolved receivers under one short-circuit flag; any null part declines the whole union.
     *
     * An element or related model, a resource collection's items, an intersection and keys declared strings survive
     * only when every part agrees on them; a collection is keyed, a model unsaved or attributeless, and a receiver read
     * by declarations or guarded, when any part is, and a surviving element guarded where any part's is.
     *
     * @param  list<ReceiverType|null>  $types
     */
    private function merge(array $types, bool $shortCircuits): ?ReceiverType
    {
        $parts = [];
        $classes = [];
        $elementModels = [];
        $relatedModels = [];
        $collectedElements = [];
        $collectedItems = true;
        $unsaved = false;
        $keyed = false;
        $attributeless = false;
        $filled = null;
        $declaredOnly = false;
        $guarded = false;
        $guardsElement = false;
        $stringKeyed = true;

        foreach ($types as $type) {
            if ($type === null) {
                return null;
            }

            $parts[] = $type;
            $classes = [...$classes, ...$type->classes];
            $elementModels[$type->elementModel ?? ''] = $type->elementModel;
            $relatedModels[$type->relatedModel ?? ''] = $type->relatedModel;
            $collectedElements[$type->collectedElement['fqcn'] ?? ''] = $type->collectedElement;
            $collectedItems = $collectedItems && $type->collectedItems;
            $unsaved = $unsaved || $type->unsaved;
            $keyed = $keyed || $type->keyed;
            $attributeless = $attributeless || $type->attributeless;
            $declaredOnly = $declaredOnly || $type->declaredOnly;
            $guarded = $guarded || $type->guarded;
            $guardsElement = $guardsElement || $type->guardsElement;
            $stringKeyed = $stringKeyed && $type->stringKeyed;
            if ($type->attributeless) {
                // Each part with no attributes holds only the columns written into all of them.
                $filled = $filled === null ? $type->filled : array_values(array_intersect($filled, $type->filled));
            }
        }

        if ($classes === []) {
            return null;
        }

        $elementModel = count($elementModels) === 1 ? reset($elementModels) : null;

        return new ReceiverType(
            classes: array_values(array_unique($classes)),
            shortCircuits: $shortCircuits,
            elementModel: $elementModel,
            relatedModel: count($relatedModels) === 1 ? reset($relatedModels) : null,
            unsaved: $unsaved,
            collectedItems: $collectedItems,
            collectedElement: count($collectedElements) === 1 ? reset($collectedElements) : null,
            keyed: $keyed,
            attributeless: $attributeless,
            filled: $filled ?? [],
            declaredOnly: $declaredOnly,
            intersections: ReceiverType::agreedIntersections($parts),
            guarded: $guarded,
            guardsElement: $elementModel !== null && $guardsElement,
            stringKeyed: $stringKeyed,
        );
    }

    /**
     * A member's value read through a receiver spelled `self`, `static` or `parent`, marked as that receiver is: the
     * class it names may be a subclass's override's too. A guarded receiver's member is guarded as well.
     */
    private function carried(ReceiverType $receiver, ?ReceiverType $member): ?ReceiverType
    {
        $member = $receiver->guarded ? $member?->asGuarded() : $member;

        return $receiver->declaredOnly ? $member?->asDeclaredOnly() : $member;
    }

    /**
     * What a collection's element yields, an item or a query of the items, guarded where only a declared list names
     * the element.
     */
    private function yielded(ReceiverType $receiver, ReceiverType $item): ReceiverType
    {
        return $receiver->guardsElement ? $item->asGuarded() : $item;
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
        $keepsKeys = CollectionSteps::keepsKeys($method);
        $keepsItems = CollectionSteps::keepsItems($method);

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

        return $receiver->keepingItems(
            match ($method) {
                'collect', 'toBase' => [Collection::class],
                'lazy' => [LazyCollection::class],
                default => $receiver->classes,
            },
            $shortCircuits,
            $keepsKeys
                ? $receiver->keyed
                : CollectionSteps::keyedAfter($call, $method, $receiver->keyed, $receiver->within([EloquentCollection::class])),
        );
    }

    /**
     * What a `merge()` or `concat()` of an empty list or a list of the same model holds: the same kind of items.
     */
    private function addedItems(
        MethodCall|NullsafeMethodCall $call,
        string $method,
        ReceiverType $receiver,
        bool $shortCircuits,
        AnalysisScope $scope,
    ): ?ReceiverType {
        $added = count($call->getArgs()) === 1 && ! $call->getArgs()[0]->unpack ? $call->getArgs()[0]->value : null;

        if ($added === null || $receiver->elementModel === null || $receiver->collectedItems || ! CollectionSteps::addsItems($method)
            || ! $receiver->within([Enumerable::class])
        ) {
            return null;
        }

        $held = $added instanceof Array_ ? null : $this->resolve($added, $scope);
        $same = ($added instanceof Array_ && $added->items === [])
            || ($held?->elementModel === $receiver->elementModel && $held->within([Enumerable::class]));

        return $same ? $receiver->keepingItems($receiver->classes, $shortCircuits, $receiver->keyed) : null;
    }

    /**
     * The item a call hands back: a collection's element model by first(), last(), firstWhere() or an Eloquent
     * collection's find() of one key, or a resource collection's element by first() or last(), which a paginator's
     * `__call()` runs on its collection; none where the call passes a default Laravel returns where no item matches.
     *
     * @return class-string|null
     */
    private function collectedElement(MethodCall|NullsafeMethodCall $call, string $method, ReceiverType $receiver): ?string
    {
        if ($receiver->collectedItems) {
            return CollectionSteps::elementCall($call, $method) ? $receiver->collectedElement['fqcn'] ?? null : null;
        }

        if ($receiver->elementModel === null) {
            return null;
        }

        if ($method === 'find') {
            return $this->findsOne($call, $receiver) ? $receiver->elementModel : null;
        }

        if (! in_array($method, self::COLLECTION_ELEMENTS, true) || ! $receiver->within([Collection::class])) {
            return null;
        }

        return CollectionSteps::passesDefault($call, $method) ? null : $receiver->elementModel;
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
        return array_all($receiver->classes, fn (string $class): bool => DeclaredClasses::returnClasses($class, $method) === [$class]);
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
     * Whether a receiver names no class narrower than a binding's: each class it names is one the binding already is,
     * or it names none.
     */
    private function namesNothingNarrower(?ReceiverType $held, string $bound): bool
    {
        return $held === null || array_all($held->classes, fn (string $class): bool => is_a($bound, $class, true));
    }
}
