<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * The mutable state threaded through one ResourceAstAnalyzer::analyze() call: the subject under
 * analysis and its backing model, plus the closure/spread bookkeeping that makes local variables,
 * whenLoaded relations, and recursive spreads resolve correctly as traversal descends.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type CollectedElement from ResourceCollections
 *
 * @phpstan-type ClosureParamExprBindingsMap array<string, Expr>
 * @phpstan-type VarClassBindingsMap array<string, non-empty-list<class-string>>
 * @phpstan-type VarGuardBinding array{classes: non-empty-list<class-string>, after: int, before: int|null}
 * @phpstan-type VarGuardBindingsMap array<string, non-empty-list<VarGuardBinding>>
 * @phpstan-type NarrowedRead array{list<string>, ReceiverType}
 * @phpstan-type NonNullRead array{read: Expr, after: int|null, before: int|null}
 * @phpstan-type NonNullReadsMap array<string, non-empty-list<NonNullRead>>
 * @phpstan-type VarDocBinding array{
 *      type: string,
 *      context: ReflectionClass<object>,
 *      after: int,
 *      before: int|null,
 *      expr: Expr,
 *      nested?: true
 * }
 * @phpstan-type VarDocBindingsMap array<string, non-empty-list<VarDocBinding>>
 * @phpstan-type VarModelBindingsMap array<string, class-string<Model>>
 * @phpstan-type VarCollectionBindingsMap array<string, array{type: string, modelFqcn: class-string<Model>}>
 * @phpstan-type VarValueBindingsMap array<string, ValueExpressionResult>
 * @phpstan-type LocalVarBindingsMap array<string, Expr>
 * @phpstan-type ByReferenceForm array{
 *      effect: ByReferenceEffect,
 *      values: list<Expr>,
 *      path: list<string>,
 *      callback: ArrowFunction|ClosureExpr|null,
 *      after: int,
 *      before: int|null,
 *      sole: bool
 * }
 * @phpstan-type BindingGap array{0: int, 1: int, 2: list<Expr>|null, 3: bool, 4: bool, 5?: list<Expr>}
 * @phpstan-type BindingGapsMap array<string, non-empty-list<BindingGap>>
 * @phpstan-type ByReferenceFormsMap array<string, non-empty-list<ByReferenceForm>>
 * @phpstan-type RequestVarNamesMap array<string, class-string<Request>>
 * @phpstan-type KeyBuiltLocalsMap array<string, list<Node\Stmt>>
 * @phpstan-type NameBindingsSnapshot array{
 *      closureParamExprBindings: ClosureParamExprBindingsMap,
 *      varClassBindings: VarClassBindingsMap,
 *      varGuardBindings: VarGuardBindingsMap,
 *      nonNullReads: NonNullReadsMap,
 *      varDocBindings: VarDocBindingsMap,
 *      varModelBindings: VarModelBindingsMap,
 *      varDeclaredBindings: VarClassBindingsMap,
 *      varCollectionBindings: VarCollectionBindingsMap,
 *      varValueBindings: VarValueBindingsMap,
 *      localVarBindings: LocalVarBindingsMap,
 *      byReferenceForms: ByReferenceFormsMap,
 *      requestVarNames: RequestVarNamesMap,
 *      claimedClosures: array<int, true>,
 *      bindingEnds: array<string, int>,
 *      bindingGaps: BindingGapsMap,
 *      keyBuiltLocals: KeyBuiltLocalsMap,
 *      passedValues: ClosureParamExprBindingsMap
 * }
 * @phpstan-type ScopeState array{
 *      bindings: NameBindingsSnapshot,
 *      provenProxy: non-empty-list<class-string>|null,
 *      modelClass: class-string<Model>|null,
 *      forwardsUndeclaredMembersTo: class-string|null,
 *      declaringFileClass: ReflectionClass<object>,
 *      declaringClass: ReflectionClass<object>,
 *      runtimeReflection: ReflectionClass<object>,
 *      resolvingLocalVars: array<string, true>,
 *      readingLocals: array<string, true>,
 *      visitedSpreadMethods: array<string, true>,
 *      narrowedReads: list<NarrowedRead>,
 *      appliedProxyProof: VarGuardBinding|null,
 *      filtersNestedResources: bool,
 *      mapResultArrayed: bool
 * }
 *
 * @internal
 */
final class AnalysisScope
{
    /** The varGuardBindings key of the proofs on an API resource's `$this->resource`, a name no variable spells. */
    public const string PROXY_GUARDS = '$this->resource';

    /**
     * Whether a body run on an object the code holds, such as a receiver, the model `$this->resource` forwards to or a
     * method macro's callable, only assumes its class: always, since the object may be a subclass of the class known.
     */
    public const bool ASSUMES_HELD_OBJECT = true;

    /** Every name-keyed binding table, empty: a function scope no caller's name reaches. */
    public const array NO_NAME_BINDINGS = [
        'closureParamExprBindings' => [],
        'varClassBindings' => [],
        'varGuardBindings' => [],
        'nonNullReads' => [],
        'varDocBindings' => [],
        'varModelBindings' => [],
        'varDeclaredBindings' => [],
        'varCollectionBindings' => [],
        'varValueBindings' => [],
        'localVarBindings' => [],
        'byReferenceForms' => [],
        'requestVarNames' => [],
        'claimedClosures' => [],
        'bindingEnds' => [],
        'bindingGaps' => [],
        'keyBuiltLocals' => [],
        'passedValues' => [],
    ];

    /**
     * Wrapped class from an `instanceof` guard in toArray(); fallback when resolveClassOnProperty() returns null.
     *
     * @var class-string|null
     */
    public ?string $instanceOfWrappedClass = null;

    /**
     * The backing class `$this->resource` holds, or null; null on a resource collection, see proxyReceiver().
     * ReceiverClassResolver::proxyHeld() weighs it against a redeclared `$resource`. Derived by the constructor,
     * rebase() and setBackingModel(); ResourceAstAnalyzer re-derives it and TernaryHandler narrows it.
     *
     * @var class-string|null
     */
    public ?string $forwardsUndeclaredMembersTo = null;

    /**
     * The classes an `instanceof` proof holds `$this->resource` to inside the ternary arm, or the value in an `if`
     * block, it proves, or null. Set and restored by NarrowsInstanceofSubjects; ReceiverClassResolver reads it before
     * the redeclaration and the backing class.
     *
     * @var non-empty-list<class-string>|null
     */
    public ?array $provenProxy = null;

    /**
     * The `$this` read paths, as property names outermost first, with what an `instanceof` ternary's proven arm holds
     * each to while it resolves, innermost last. Set and restored by NarrowsInstanceofSubjects; ReceiverClassResolver
     * reads them.
     *
     * @var list<NarrowedRead>
     */
    public array $narrowedReads = [];

    /**
     * The proof on `$this->resource` a value inside its span resolves under while the proof leaves provenProxy null,
     * so a value nested in that one skips the lookup the proof answered. Set by NarrowsInstanceofSubjects.
     *
     * @var VarGuardBinding|null
     */
    public ?array $appliedProxyProof = null;

    /**
     * What each item the runtime class, a ResourceCollection, collects publishes as: the element `$this->collection`
     * lists and `$this->resource` holds; see ResourceCollections::collectedElement(). Set by seedCollectionFacts(), on
     * rebase() and for any scope ResourceAstAnalyzer reads.
     *
     * @var CollectedElement|null
     */
    public ?array $collectedElement = null;

    /**
     * False while MethodReturnTypeResolver's body fallback analyzes a method: it flattens the shape into a type string
     * with no FQCN channel and drops the whole shape once a value names a token. Filter code reads it to publish the
     * most specific answer that names none.
     */
    public bool $carriesImports = true;

    /**
     * Whether the response the runtime class's analysis feeds re-indexes a numeric-keyed array into a list, as an API
     * resource that does not preserve keys does: a list a key-keeping sort reorders is still sent as one. Set by
     * seedCollectionFacts(), on rebase() and for any scope ResourceAstAnalyzer reads.
     */
    public bool $reindexesNumericKeys = false;

    /**
     * Whether JsonResource::filter() reaches the value being read, so a nested resource around null is sent as null.
     * False while a map() body resolves whose result stays a Collection: filter() recurses into arrays only.
     */
    public bool $filtersNestedResources = true;

    /**
     * Whether the map() call being read is turned into an array by a trailing all(), so filter() reaches its items.
     * VariableHandler's values()/all() arm sets it for its receiver, and only VariableHandler's map arm reads it.
     */
    public bool $mapResultArrayed = false;

    /**
     * Closure parameter names bound to an expression: a required `when()` parameter to its condition's `$this->prop`,
     * so `EnumResource::make($status)` resolves like `EnumResource::make($this->status)`; a `transform()` callback's
     * to the `$this->prop` it is passed; and a `whenHas()`/`whenExistsLoaded()` value closure's to the attribute read.
     *
     * @var ClosureParamExprBindingsMap
     */
    public array $closureParamExprBindings = [];

    /**
     * Variables a morphTo `whenLoaded()` parameter's targets bind to one of some classes. Read before varModelBindings,
     * and narrowed by varGuardBindings like any binding. Scoped: writers save and restore around the body.
     *
     * @var VarClassBindingsMap
     */
    public array $varClassBindings = [];

    /**
     * Variables an `instanceof` test proves to hold one of some classes, each proof for the reads between its offsets:
     * the block a test runs, the rest of a block past a guard that always leaves it, or a ternary's arm while it
     * resolves. A proof holds the classes of the one around it too, and narrows whatever the variable is bound to.
     *
     * The proofs on an API resource's `$this->resource`, for the blocks a test runs, sit under PROXY_GUARDS. Each holds
     * for the offsets past its `after` and through its `before`.
     *
     * @var VarGuardBindingsMap
     */
    public array $varGuardBindings = [];

    /**
     * Read paths a guard proves non-null, keyed by the variable each starts at: a condition's for the value, arm or
     * block it runs, an exit's for the rest of its block, each for the offsets past its `after` and short of its
     * `before`, a bound it leaves null being open.
     *
     * A resource built around one never serializes as null. Scoped like varClassBindings, and a closure parameter, or a
     * write in a closure's or a called method's body, drops the proofs of its name.
     *
     * @var NonNullReadsMap
     */
    public array $nonNullReads = [];

    /**
     * Variables an inline `@var` on their assignment declares a type for: the type as written, the class whose file
     * resolves its names, the value assigned, and the reads it holds for, past the assignment and before the top-level
     * statement that next writes the variable. Written by CollectsLocalVarBindings, and scoped like localVarBindings.
     *
     * Each holds for the offsets past its `after` and short of its `before`.
     *
     * @var VarDocBindingsMap
     */
    public array $varDocBindings = [];

    /**
     * Names bound to a model class, so `$var`, `$var->prop`, `$var->method()` resolve against it: a to-one whenLoaded,
     * map or transform() closure param, scoped to its closure's body, and, method-wide, a foreach variable over a
     * many-relation or a concrete, non-null model parameter ParameterBinder seeds.
     *
     * @var VarModelBindingsMap
     */
    public array $varModelBindings = [];

    /**
     * Parameters a method read apart from its call declares, or is passed, a class-only type for, other than the one
     * concrete model varModelBindings holds: the floor every narrowing refines, so it is read after them.
     *
     * @var VarClassBindingsMap
     */
    public array $varDeclaredBindings = [];

    /**
     * Closure params bound to a whole relation collection rather than one element — a to-many
     * `whenLoaded` param. Read for a bare return of the param, and as the element-model fallback
     * for an untyped `->map()` closure param.
     *
     * @var VarCollectionBindingsMap
     */
    public array $varCollectionBindings = [];

    /**
     * Closure params bound to an already-resolved value rather than to a class — a `collect(...)->map()` param, a
     * `transform()` param passed a value, a variadic param's list, a count, or the value of a default a call
     * leaves in place. Scoped: writers save and restore around the body.
     *
     * @var VarValueBindingsMap
     */
    public array $varValueBindings = [];

    /**
     * Top-level `$var = expr;` bindings for the method last analyzed, so a bare `Variable` value
     * expression resolves through its bound expression instead of degrading to unknown. Only variables
     * written exactly once are recorded; analyzeThisMethodSpread() saves and restores this per method.
     *
     * @var LocalVarBindingsMap
     */
    public array $localVarBindings = [];

    /**
     * The by-reference calls that keep a variable's value, or the member a key path names, only in some forms, by the
     * name each writes: `sort()` keeps a list, `asort()` an object. Written by CollectsLocalVarBindings, which binds
     * the variable across them; a read past a call, and before the end of a block it returns from, takes its form.
     *
     * Each holds for the offsets past its `after` and through its `before`. A form's `sole` says no write but calls of
     * its effect reaches the name.
     *
     * @var ByReferenceFormsMap
     */
    public array $byReferenceForms = [];

    /**
     * The offset at which each name stops holding the binding it came into the body with, a parameter's or a seeded
     * one: the body's first write that replaces it with another class. A read at it still holds the binding; a read
     * past it sees that write instead.
     *
     * @var array<string, int>
     */
    public array $bindingEnds = [];

    /**
     * The spans where a name may not hold the binding it came in with though it takes it back past them: from a write
     * in a block that always leaves the function to that block's end. A name with no binding has one for each `$x = …;`
     * nested in a block, from past that statement to its block's end or the block's statement holding the next write.
     *
     * A name written more than once, or a parameter a write may replace, has one past each statement that writes it.
     * Each holds for the offsets past its first and through its second.
     *
     * Each carries its writes' values, or null where one assigns none the scan reads, whether the old binding may still
     * hold, past a write that may not run, and whether a receiver keeps it past a write whose value names no class. A
     * parameter's gap also lists the values its nested writes added, past which an earlier value still reaches.
     *
     * @var BindingGapsMap
     */
    public array $bindingGaps = [];

    /**
     * Locals the body assigns whole and builds through key writes or `+=`, which no binding holds and no reference can
     * write, each with the body's statements, so a read no binding types walks the writes before it.
     *
     * @var KeyBuiltLocalsMap
     */
    public array $keyBuiltLocals = [];

    /**
     * What a call passed each callback parameter no binding holds, since nothing types the value, so a join that leaves
     * that value out names the expression passed. No reader takes a binding from it.
     *
     * @var ClosureParamExprBindingsMap
     */
    public array $passedValues = [];

    /**
     * Re-entrancy guard: the bindings mid-resolution, each keyed by its name and the bound expression, so a cyclic
     * binding such as `$a = $b; $b = $a;` reads unknown instead of recursing, while `$s = $s . 'x'` reads the old `$s`.
     * A by-reference call's forms and a key-built local's walk, both VariableHandler's, share one key: the name alone.
     *
     * @var array<string, true>
     */
    public array $resolvingLocalVars = [];

    /**
     * The locals ResourceAstAnalyzer is reading the one written value of as a whole array, so a value naming its own
     * local stops. A spread method's body starts with none, whatever local read its caller is in.
     *
     * @var array<string, true>
     */
    public array $readingLocals = [];

    /**
     * Spread methods currently on the analysis stack, by declared name, so a method that spreads itself — directly or
     * through a cycle — degrades to an empty analysis instead of recursing until memory runs out.
     *
     * @var array<string, true>
     */
    public array $visitedSpreadMethods = [];

    /**
     * Variable names holding an `Illuminate\Http\Request`, keyed to the bound class so the Request
     * method rules fire on `$request->user()`, stay off an unrelated same-named receiver, and
     * `validated()` resolves against the actual `FormRequest` subclass rather than the base class.
     *
     * @var RequestVarNamesMap
     */
    public array $requestVarNames = [];

    /**
     * Closures, by spl_object_id(), whose parameters a writer has claimed — released, then bound to what the call
     * passes or what they hold without it, save a required when() parameter, bound to its condition's `$this->prop`
     * though that call throws — so ClosureHandler leaves them alone. Restored with the name tables.
     *
     * @var array<int, true>
     */
    public array $claimedClosures = [];

    /**
     * The class, or trait, whose file holds the body under analysis, so an inline `@var` in that body resolves its
     * names against that file's imports. The subject, unless the body was located in another file.
     *
     * @var ReflectionClass<object>
     */
    public ReflectionClass $declaringFileClass;

    /**
     * The class `self` names in the body under analysis, and whose parent `parent` names: its method's declaring class,
     * which PHP reports as the using class for a trait's method. The subject, unless the body is an ancestor's.
     *
     * @var ReflectionClass<object>
     */
    public ReflectionClass $declaringClass;

    /**
     * The runtime class: the class `$this` and `static` are when the body runs. The subject, unless ResourceAstAnalyzer
     * reads an ancestor's body for a subclass, or a method of an instance the body builds, which within() puts back.
     *
     * @var ReflectionClass<object>
     */
    public ReflectionClass $runtimeReflection;

    /**
     * Whether the runtime class is only assumed: a body a receiver's call or an unrelated class's name reaches reads
     * the class declared or named, whose subclass the object may be. Set once, by the builder; see knownRuntime().
     */
    public bool $assumesRuntime = false;

    /**
     * @param  ReflectionClass<object>  $subjectReflection  the resource (or other AST subject) under analysis
     * @param  class-string<Model>|null  $modelClass  its resolved backing model, if any. Scoped, not fixed:
     *                                                TernaryHandler narrows it for an `instanceof` true arm and
     *                                                restores it, below AstEngine's `analysis:` memo key.
     */
    public function __construct(
        public ReflectionClass $subjectReflection,
        public ?string $modelClass = null,
    ) {
        $this->declaringFileClass = $subjectReflection;
        $this->declaringClass = $subjectReflection;
        $this->runtimeReflection = $subjectReflection;

        // The subject alone decides this, so every scope carries it without the builder having to remember;
        // ResourceAstAnalyzer re-derives it once an instanceof guard supplies a backing the constructor lacked.
        $this->forwardsUndeclaredMembersTo = self::proxyTargetFor($subjectReflection, $modelClass);
    }

    /**
     * The class whose declaration `$this->$member` reaches from a body the declaring class holds: that class when it
     * declares the member private, since PHP binds a private member to the calling scope, else the runtime class.
     *
     * @param  ReflectionClass<object>  $declaring
     * @param  ReflectionClass<object>  $runtime
     * @return ReflectionClass<object>
     */
    public static function memberClassOf(ReflectionClass $declaring, ReflectionClass $runtime, string $member, bool $isMethod): ReflectionClass
    {
        $declared = match (true) {
            $isMethod => $declaring->hasMethod($member) ? $declaring->getMethod($member) : null,
            default => $declaring->hasProperty($member) ? $declaring->getProperty($member) : null,
        };

        // Reflection lists an ancestor's private method on its subclasses too, though no subclass body can call it.
        $ownPrivate = $declared !== null && $declared->isPrivate()
            && $declared->getDeclaringClass()->getName() === $declaring->getName();

        return $ownPrivate ? $declaring : $runtime;
    }

    /**
     * Whether a body run on an object the container fills only assumes its class: unless the class is final, the
     * container may bind a subclass in its place.
     */
    public static function assumesInjected(bool $isFinal): bool
    {
        return ! $isFinal;
    }

    /**
     * The class a subject's `$this->resource` holds and its undeclared members forward to, or null where it has no
     * proxy or names no one class. The one home of the proxy target, for every scope and every narrowing of it; a
     * ResourceCollection's items are proxyReceiver()'s.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  class-string|null  $backing  the backing model, or the class an instanceof guard names
     * @return class-string|null
     */
    public static function proxyTargetFor(ReflectionClass $subject, ?string $backing): ?string
    {
        return $subject->isSubclassOf(JsonResource::class) && ! $subject->isSubclassOf(ResourceCollection::class)
            ? $backing
            : null;
    }

    /**
     * A closure's first parameter name, or null for none, a variadic one, or an expression that is no closure.
     */
    public static function firstParameterName(Expr $closure): ?string
    {
        $first = $closure instanceof ArrowFunction || $closure instanceof ClosureExpr ? $closure->params[0] ?? null : null;

        return $first !== null && ! $first->variadic && $first->var instanceof Variable && is_string($first->var->name)
            ? $first->var->name
            : null;
    }

    /**
     * Move the scope to another subject and backing model, re-deriving the proxy target and the collection facts from
     * them: the one way a builder rebases a scope after construction.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  class-string<Model>|null  $modelClass
     */
    public function rebase(ReflectionClass $subject, ?string $modelClass): void
    {
        $this->subjectReflection = $subject;
        $this->runtimeReflection = $subject;
        $this->setBackingModel($modelClass);
        $this->seedCollectionFacts($subject);
    }

    /**
     * Back the subject by another model, re-deriving the proxy target; the collection facts stay the runtime class's.
     *
     * @param  class-string<Model>|null  $modelClass
     */
    public function setBackingModel(?string $modelClass): void
    {
        $this->modelClass = $modelClass;
        $this->forwardsUndeclaredMembersTo = self::proxyTargetFor($this->subjectReflection, $modelClass);
    }

    /**
     * Derive the collection facts from the runtime class, which an ancestor's body runs as: `reindexesNumericKeys` and
     * `collectedElement`, from ResourceCollections, once a run per class.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    public function seedCollectionFacts(ReflectionClass $runtime): void
    {
        [$this->reindexesNumericKeys, $this->collectedElement] = resolve(AnalysisMemo::class)->remember(
            'collection-facts:'.$runtime->getName(),
            static fn (): array => [
                ResourceCollections::reindexesNumericKeys($runtime),
                $runtime->isSubclassOf(ResourceCollection::class) ? ResourceCollections::collectedElement($runtime) : null,
            ],
        );
    }

    /**
     * Whether the subject's `$this->resource` is the proxy to what it wraps: every API resource has one, model or not,
     * and no other subject does, so in a model's own body, a controller or an event it is a member like any other.
     */
    public function hasResourceProxy(): bool
    {
        return $this->subjectReflection->isSubclassOf(JsonResource::class);
    }

    /**
     * The class whose declaration `$this->$member` reaches from the body under analysis: the declaring class when it
     * declares that member private, since PHP binds a private member to the calling scope, else the runtime class.
     *
     * @return ReflectionClass<object>
     */
    public function thisMemberClass(string $member, bool $isMethod): ReflectionClass
    {
        return self::memberClassOf($this->declaringClass, $this->runtimeReflection, $member, $isMethod);
    }

    /**
     * The class `self`, `static` or `parent` names in the body under analysis: the declaring class, the runtime class,
     * or the declaring class's parent. Null for any other name, and for a `parent` the declaring class lacks.
     *
     * @return ReflectionClass<object>|null
     */
    public function relativeClass(Name $name): ?ReflectionClass
    {
        return match ($name->toLowerString()) {
            'self' => $this->declaringClass,
            'static' => $this->runtimeReflection,
            'parent' => $this->declaringClass->getParentClass() ?: null,
            default => null,
        };
    }

    /**
     * The class a static-syntax call on the subject reaches from the body under analysis: `$this::` and `static::`
     * the runtime class, since PHP looks such a call up there with no private rule; `self::` the declaring class,
     * `parent::` its parent, or the subject where it has none; a name the subject.
     *
     * @return ReflectionClass<object>
     */
    public function staticCallClass(StaticCall $call): ReflectionClass
    {
        $class = $call->class;

        return match (true) {
            $class instanceof Variable => $class->name === 'this' ? $this->runtimeReflection : $this->subjectReflection,
            $class instanceof Name => $this->relativeClass($class) ?? $this->subjectReflection,
            default => $this->subjectReflection,
        };
    }

    /**
     * Whether a static-syntax call hands its callee the runtime class as `static`: `self::`, `static::` and `parent::`
     * forward it, and PHP runs a non-static method named through the runtime class or an ancestor on `$this`.
     */
    public function forwardsRuntimeClass(StaticCall $call, string $className, string $methodName): bool
    {
        if (! $call->class instanceof Name) {
            return false;
        }

        return $call->class->isSpecialClassName()
            || (method_exists($className, $methodName)
                && ! new ReflectionMethod($className, $methodName)->isStatic()
                && is_a($this->runtimeReflection->getName(), $className, true));
    }

    /**
     * Whether the body a call reaches only assumes its runtime class: one the call runs on this scope's runtime
     * instance keeps this scope's flag, and any other is assumed.
     */
    public function assumesCallee(bool $onRuntimeInstance): bool
    {
        return ! $onRuntimeInstance || $this->assumesRuntime;
    }

    /**
     * Whether a body run on an instance of a class this scope builds only assumes it: a build is exact, unless the
     * class is the one an assumed scope's `static` stands for.
     */
    public function assumesBuilt(string $class): bool
    {
        return $this->assumesRuntime && $class === $this->runtimeReflection->getName();
    }

    /**
     * The runtime class where the scope knows it, null where it only assumes it: the published class's, or a helper's
     * reached from such a scope through `$this`, `self`, `static`, `parent`, an ancestor's name or a `static` return.
     *
     * @return ReflectionClass<object>|null
     */
    public function knownRuntime(): ?ReflectionClass
    {
        return $this->assumesRuntime ? null : $this->runtimeReflection;
    }

    /**
     * What `$this->resource` holds as a receiver: the proxy target, or a ResourceCollection's items, which Laravel
     * keeps in a Support\Collection or the paginator it was given, so each concrete class it builds is one arm.
     */
    public function proxyReceiver(): ?ReceiverType
    {
        if ($this->subjectReflection->isSubclassOf(ResourceCollection::class)) {
            return new ReceiverType(
                [Collection::class, LengthAwarePaginator::class, Paginator::class, CursorPaginator::class],
                collectedItems: true,
                collectedElement: $this->collectedElement,
            );
        }

        return $this->forwardsUndeclaredMembersTo === null ? null : ReceiverType::of($this->forwardsUndeclaredMembersTo);
    }

    /**
     * The inline `@var` binding a read of a variable falls within, or null.
     *
     * @return VarDocBinding|null
     */
    public function declaredAt(Variable $variable): ?array
    {
        if (! is_string($variable->name)) {
            return null;
        }

        $position = $variable->getStartFilePos();

        foreach ($this->varDocBindings[$variable->name] ?? [] as $binding) {
            if ($position > $binding['after'] && ($binding['before'] === null || $position < $binding['before'])) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * The inline `@var` on a write nested in a block that assigned a value, which still types that value where a join
     * carries it past the block, or null.
     *
     * @return VarDocBinding|null
     */
    public function declaredWrite(string $name, Expr $value): ?array
    {
        return array_find(
            $this->varDocBindings[$name] ?? [],
            fn (array $binding): bool => isset($binding['nested']) && $binding['expr'] === $value,
        );
    }

    /**
     * The `instanceof` proof a read of a variable falls within, the one that starts latest, or null. On a tie the one
     * bound last wins, since it holds the classes of the one bound before it.
     *
     * @return VarGuardBinding|null
     */
    public function guardAt(Variable $variable): ?array
    {
        return is_string($variable->name) ? $this->proofAt($variable->name, $variable->getStartFilePos()) : null;
    }

    /**
     * The `instanceof` proof on a name, PROXY_GUARDS included, an offset falls within: the one that starts latest, or
     * on a tie the one bound last; null where none holds.
     *
     * @return VarGuardBinding|null
     */
    public function proofAt(string $name, int $offset): ?array
    {
        return self::latestGuard($this->varGuardBindings[$name] ?? [], $offset);
    }

    /**
     * Whether a read of a variable still sees the binding its name came into the body with, not a write the body made.
     */
    public function holdsBinding(Variable $variable): bool
    {
        return ! is_string($variable->name)
            || self::holdsAt($variable->name, $variable->getStartFilePos(), $this->bindingEnds, $this->bindingGaps);
    }

    /**
     * The gap a read falls inside, the one whose write came latest, or null when it falls in none.
     *
     * @return BindingGap|null
     */
    public function gapAt(Variable $variable): ?array
    {
        return is_string($variable->name)
            ? self::latestGap($this->bindingGaps[$variable->name] ?? [], $variable->getStartFilePos())
            : null;
    }

    /**
     * The by-reference forms a read of a variable falls within, in the order the scan found their calls.
     *
     * @return list<ByReferenceForm>
     */
    public function formsAt(Variable $variable): array
    {
        if (! is_string($variable->name) || ! isset($this->byReferenceForms[$variable->name])) {
            return [];
        }

        $position = $variable->getStartFilePos();

        return array_values(array_filter(
            $this->byReferenceForms[$variable->name],
            fn (array $form): bool => $position > $form['after'] && ($form['before'] === null || $position <= $form['before']),
        ));
    }

    /**
     * The value a local written once holds at a read, but where a by-reference call reshapes it: none where a
     * parameter's binding or a join holds there instead, as VariableHandler::boundValue() reads it.
     */
    public function literalWriteAt(Variable $read): ?Expr
    {
        $name = $read->name;

        if (! is_string($name) || ($this->bindsName($name) && $this->holdsBinding($read)) || $this->gapAt($read) !== null) {
            return null;
        }

        return $this->literalLocalBinding($name);
    }

    /**
     * Run a read with $enter's changes to the scope, then put back every name table and ambient field, even on a throw:
     * the one way a reader binds names, sets the caller aside or narrows a subject for one body, arm or value.
     *
     * @template TRead
     *
     * @param  Closure(self): void  $enter
     * @param  Closure(): TRead  $read
     * @return TRead
     */
    public function within(Closure $enter, Closure $read): mixed
    {
        $state = $this->state();

        try {
            $enter($this);

            return $read();
        } finally {
            $this->restoreState($state);
        }
    }

    /**
     * Run a read with one gap of a name lifted, so a value a write inside it assigns reads the name as it was before.
     *
     * @template TRead
     *
     * @param  BindingGap  $gap
     * @param  Closure(): TRead  $read
     * @return TRead
     */
    public function beforeGap(string $name, array $gap, Closure $read): mixed
    {
        $gaps = $this->bindingGaps[$name] ?? null;
        $others = array_values(array_filter($gaps ?? [], fn (array $other): bool => $other !== $gap));

        if ($others === []) {
            unset($this->bindingGaps[$name]);
        } else {
            $this->bindingGaps[$name] = $others;
        }

        try {
            return $read();
        } finally {
            if ($gaps === null) {
                unset($this->bindingGaps[$name]);
            } else {
                $this->bindingGaps[$name] = $gaps;
            }
        }
    }

    /**
     * Run a read of the expression a variable is bound to, or give null while that same binding is mid-resolution.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @return TRead|null
     */
    public function resolvingBinding(string $name, Expr $bound, Closure $read): mixed
    {
        $key = self::resolvingKey($name, $bound);

        if (isset($this->resolvingLocalVars[$key])) {
            return null;
        }

        $this->resolvingLocalVars[$key] = true;

        try {
            return $read();
        } finally {
            unset($this->resolvingLocalVars[$key]);
        }
    }

    /**
     * Run a read keyed by a name alone, or give null while a read of that name is under way, as VariableHandler's
     * by-reference forms and key-built locals do.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @return TRead|null
     */
    public function resolvingName(string $name, Closure $read): mixed
    {
        if (isset($this->resolvingLocalVars[$name])) {
            return null;
        }

        $this->resolvingLocalVars[$name] = true;

        try {
            return $read();
        } finally {
            unset($this->resolvingLocalVars[$name]);
        }
    }

    /**
     * Whether a variable's binding to an expression is mid-resolution, so reading it again would recurse.
     */
    public function resolvesBinding(string $name, Expr $bound): bool
    {
        return isset($this->resolvingLocalVars[self::resolvingKey($name, $bound)]);
    }

    /**
     * Whether a name holds a parameter's or a seeded binding, which a body write can end, rather than only the body's.
     */
    public function bindsName(string $name): bool
    {
        return array_any($this->parameterBindingTables(), fn (array $table): bool => isset($table[$name]));
    }

    /**
     * Whether a name holds a parameter's or a seeded binding other than a plain value, which may name a class.
     */
    public function bindsClassName(string $name): bool
    {
        return array_any($this->classBindingTables(), fn (array $table): bool => isset($table[$name]));
    }

    /**
     * Whether any name holds a parameter's or a seeded binding, so a body's writes can end one.
     */
    public function bindsAnyName(): bool
    {
        return array_any($this->parameterBindingTables(), fn (array $table): bool => $table !== []);
    }

    /**
     * Capture every name-keyed binding table — each map from a variable name to what it is bound to — for a writer
     * to restore after the body.
     *
     * @return NameBindingsSnapshot
     */
    public function nameBindings(): array
    {
        return [
            'closureParamExprBindings' => $this->closureParamExprBindings,
            'varClassBindings' => $this->varClassBindings,
            'varGuardBindings' => $this->varGuardBindings,
            'nonNullReads' => $this->nonNullReads,
            'varDocBindings' => $this->varDocBindings,
            'varModelBindings' => $this->varModelBindings,
            'varDeclaredBindings' => $this->varDeclaredBindings,
            'varCollectionBindings' => $this->varCollectionBindings,
            'varValueBindings' => $this->varValueBindings,
            'localVarBindings' => $this->localVarBindings,
            'byReferenceForms' => $this->byReferenceForms,
            'requestVarNames' => $this->requestVarNames,
            'claimedClosures' => $this->claimedClosures,
            'bindingEnds' => $this->bindingEnds,
            'bindingGaps' => $this->bindingGaps,
            'keyBuiltLocals' => $this->keyBuiltLocals,
            'passedValues' => $this->passedValues,
        ];
    }

    /**
     * Restore every name-keyed binding table from a nameBindings() capture.
     *
     * @param  NameBindingsSnapshot  $snapshot
     */
    public function restoreNameBindings(array $snapshot): void
    {
        $this->closureParamExprBindings = $snapshot['closureParamExprBindings'];
        $this->varClassBindings = $snapshot['varClassBindings'];
        $this->varGuardBindings = $snapshot['varGuardBindings'];
        $this->nonNullReads = $snapshot['nonNullReads'];
        $this->varDocBindings = $snapshot['varDocBindings'];
        $this->varModelBindings = $snapshot['varModelBindings'];
        $this->varDeclaredBindings = $snapshot['varDeclaredBindings'];
        $this->varCollectionBindings = $snapshot['varCollectionBindings'];
        $this->varValueBindings = $snapshot['varValueBindings'];
        $this->localVarBindings = $snapshot['localVarBindings'];
        $this->byReferenceForms = $snapshot['byReferenceForms'];
        $this->requestVarNames = $snapshot['requestVarNames'];
        $this->claimedClosures = $snapshot['claimedClosures'];
        $this->bindingEnds = $snapshot['bindingEnds'];
        $this->bindingGaps = $snapshot['bindingGaps'];
        $this->keyBuiltLocals = $snapshot['keyBuiltLocals'];
        $this->passedValues = $snapshot['passedValues'];
    }

    /**
     * Set the caller's state aside to read $method, a method `$this` reaches, as within()'s $enter: no caller name
     * reaches its body, which reads its own file's imports and its own declaring class, and only $proofs of `$this`
     * reads hold.
     *
     * @param  NonNullReadsMap  $proofs  the caller's proofs that hold across the call
     * @param  list<list<string>>  $rewritten  the `$this` read paths the body writes whole, which no proof holds in it
     */
    public function enterSubjectMethod(ReflectionMethod $method, array $proofs, array $rewritten = []): void
    {
        // A write replaces the path and every path through it; an undeclared `$this->prop` reads through the proxy.
        $replacesProxy = $this->hasResourceProxy() && in_array(['resource'], $rewritten, true);
        $this->provenProxy = $replacesProxy ? null : $this->provenProxy;
        $this->narrowedReads = $replacesProxy ? [] : array_values(array_filter(
            $this->narrowedReads,
            fn (array $read): bool => ! array_any($rewritten, fn (array $path): bool => array_slice($read[0], 0, count($path)) === $path),
        ));

        $this->visitedSpreadMethods[$method->getName()] = true;
        $this->restoreNameBindings(self::NO_NAME_BINDINGS);
        $this->nonNullReads = $proofs;
        $this->resolvingLocalVars = [];
        $this->declaringFileClass = LaravelTsPublish::methodDeclaringFileClass($method);
        $this->declaringClass = $method->getDeclaringClass();
    }

    /**
     * Read a closure a call passes values to under its own parameter bindings: $beforeClaim makes the changes the claim
     * may drop again, every parameter name is claimed from the outer tables, $bind binds what the call passes, and each
     * parameter past the $passed values holds its default; within() puts the scope back after, a throw included.
     *
     * @template TRead
     *
     * @param  int|null  $passed  how many values the call passes; null binds no default
     * @param  Closure(): TRead  $read
     * @param  (Closure(): void)|null  $bind
     * @param  (Closure(): void)|null  $beforeClaim
     * @return TRead
     */
    public function readClaimedClosure(
        Expr $closure,
        ?int $passed,
        ExpressionEngine $engine,
        Closure $read,
        ?Closure $bind = null,
        ?Closure $beforeClaim = null,
    ): mixed {
        return $this->within(function () use ($closure, $passed, $engine, $bind, $beforeClaim): void {
            if ($beforeClaim !== null) {
                $beforeClaim();
            }

            $this->claimParameters($closure);

            if ($bind !== null) {
                $bind();
            }

            if ($passed !== null) {
                $this->bindUnpassedParameters($closure, $passed, $engine);
            }
        }, $read);
    }

    /**
     * Release a closure's parameter names before a writer binds them, marked so ClosureHandler keeps those bindings.
     */
    public function claimParameters(Expr $closure): void
    {
        if ($closure instanceof ArrowFunction || $closure instanceof ClosureExpr) {
            $this->releaseParameterNames($closure);
            $this->claimedClosures[spl_object_id($closure)] = true;
        }
    }

    /**
     * Release the parameter names of a closure no writer claimed, as ClosureHandler does before analyzing its body.
     */
    public function releaseUnclaimedParameters(Expr $closure): void
    {
        if (! $closure instanceof ArrowFunction && ! $closure instanceof ClosureExpr) {
            return;
        }

        if (! isset($this->claimedClosures[spl_object_id($closure)])) {
            $this->releaseParameterNames($closure);
        }
    }

    /**
     * Bind each parameter a call leaves without an argument, past the first $passedCount, to what it holds at runtime:
     * a variadic one an empty list, an optional one the value its default evaluates to. A required one binds nothing,
     * since the call throws, and neither does a default that cannot be typed.
     */
    public function bindUnpassedParameters(Expr $closure, int $passedCount, ExpressionEngine $engine): void
    {
        if (! $closure instanceof ArrowFunction && ! $closure instanceof ClosureExpr) {
            return;
        }

        foreach ($closure->params as $index => $param) {
            if ($index < $passedCount || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $held = match (true) {
                $param->variadic => ['type' => 'never[]', 'optional' => false],
                $param->default !== null => $this->defaultValue($param->default, $engine),
                default => null,
            };

            if ($held !== null && $held['type'] !== 'unknown') {
                $this->varValueBindings[$param->var->name] = $held;
            }

            // A readable `new` default holds that object, which the receiver resolver reads as one no row backs yet;
            // the closure's reader marks it filled where its body fills it.
            if ($held !== null && $param->default instanceof New_ && $param->default->class instanceof Name) {
                $this->closureParamExprBindings[$param->var->name] = $param->default;
            }
        }
    }

    /**
     * Bind a claimed parameter to every entry the variable its call passes held in a nameBindings() capture, but a
     * proof, which spells the read it proves under the variable's own name, and a binding end or gap, offsets in the
     * caller's body. Past its end or inside a gap, only the body's own bindings pass, a gap's one write among them.
     *
     * Inside a gap the old binding may still hold past, both pass: the parameter's every read joins the two. The
     * `instanceof` guard in force where the variable is passed holds for every read of the parameter.
     *
     * @param  NameBindingsSnapshot  $snapshot
     */
    public function copyBindings(Variable $passed, string $to, array $snapshot): void
    {
        if (! is_string($passed->name)) {
            return;
        }

        $from = $passed->name;
        $offset = $passed->getStartFilePos();
        $local = $snapshot['localVarBindings'][$from] ?? null;
        $holds = self::holdsAt($from, $offset, $snapshot['bindingEnds'], $snapshot['bindingGaps']);
        $gap = $holds ? null : self::latestGap($snapshot['bindingGaps'][$from] ?? [], $offset);
        $joins = $gap !== null && $gap[3];

        if ($joins) {
            $joined = [PHP_INT_MIN, PHP_INT_MAX, $gap[2], true, $gap[4]];
            $this->bindingGaps[$to] = [isset($gap[5]) ? [...$joined, $gap[5]] : $joined];
        } elseif (! $holds) {
            // The parameter's own binding ends before any read, so no reader guesses a class for it.
            $this->bindingEnds[$to] = PHP_INT_MIN;

            if ($gap !== null && count($gap[2] ?? []) === 1) {
                $local = $gap[2][0];
            }
        }

        if ($holds || $joins) {
            if (isset($snapshot['closureParamExprBindings'][$from])) {
                $this->closureParamExprBindings[$to] = $snapshot['closureParamExprBindings'][$from];
            }

            if (isset($snapshot['varClassBindings'][$from])) {
                $this->varClassBindings[$to] = $snapshot['varClassBindings'][$from];
            }

            if (isset($snapshot['varModelBindings'][$from])) {
                $this->varModelBindings[$to] = $snapshot['varModelBindings'][$from];
            }

            if (isset($snapshot['varDeclaredBindings'][$from])) {
                $this->varDeclaredBindings[$to] = $snapshot['varDeclaredBindings'][$from];
            }

            if (isset($snapshot['varCollectionBindings'][$from])) {
                $this->varCollectionBindings[$to] = $snapshot['varCollectionBindings'][$from];
            }

            if (isset($snapshot['varValueBindings'][$from])) {
                $this->varValueBindings[$to] = $snapshot['varValueBindings'][$from];
            }

            if (isset($snapshot['requestVarNames'][$from])) {
                $this->requestVarNames[$to] = $snapshot['requestVarNames'][$from];
            }
        }

        $guard = self::latestGuard($snapshot['varGuardBindings'][$from] ?? [], $offset);

        if ($guard !== null) {
            $this->varGuardBindings[$to] = [['classes' => $guard['classes'], 'after' => PHP_INT_MIN, 'before' => null]];
        }

        if (isset($snapshot['varDocBindings'][$from])) {
            $this->varDocBindings[$to] = $snapshot['varDocBindings'][$from];
        }

        if ($local !== null) {
            $this->localVarBindings[$to] = $local;
        }

        if (isset($snapshot['byReferenceForms'][$from])) {
            $this->byReferenceForms[$to] = $snapshot['byReferenceForms'][$from];
        }

        if (isset($snapshot['passedValues'][$from])) {
            $this->passedValues[$to] = $snapshot['passedValues'][$from];
        }
    }

    /**
     * The local bindings a reader may take by their syntax, such as an array literal's keys: each but those a
     * by-reference call keeps only in some forms, which only a read of the value's type can check.
     *
     * @return LocalVarBindingsMap
     */
    public function literalLocalBindings(): array
    {
        return array_diff_key($this->localVarBindings, $this->byReferenceForms);
    }

    /**
     * The local binding of one name a reader may take by its syntax, as literalLocalBindings() lists it, or null.
     */
    public function literalLocalBinding(string $name): ?Expr
    {
        return isset($this->byReferenceForms[$name]) ? null : ($this->localVarBindings[$name] ?? null);
    }

    /**
     * The resolvingLocalVars key of a variable's binding to an expression.
     */
    private static function resolvingKey(string $name, Expr $bound): string
    {
        return $name.'#'.spl_object_id($bound);
    }

    /**
     * Whether a name holds the binding it came into the body with at an offset, past no end and inside no gap.
     *
     * @param  array<string, int>  $ends
     * @param  BindingGapsMap  $gaps
     */
    private static function holdsAt(string $name, int $offset, array $ends, array $gaps): bool
    {
        return (! isset($ends[$name]) || $offset <= $ends[$name])
            && ! array_any($gaps[$name] ?? [], fn (array $gap): bool => $offset > $gap[0] && $offset <= $gap[1]);
    }

    /**
     * The gap of a name's that an offset falls inside, the one whose write came latest, or null when it falls in none.
     *
     * @param  list<BindingGap>  $gaps
     * @return BindingGap|null
     */
    private static function latestGap(array $gaps, int $offset): ?array
    {
        $latest = null;

        foreach ($gaps as $gap) {
            if ($offset > $gap[0] && $offset <= $gap[1] && ($latest === null || $gap[0] > $latest[0])) {
                $latest = $gap;
            }
        }

        return $latest;
    }

    /**
     * The guard of a name's that an offset falls within, the one that starts latest and, on a tie, the one bound last.
     *
     * @param  list<VarGuardBinding>  $guards
     * @return VarGuardBinding|null
     */
    private static function latestGuard(array $guards, int $offset): ?array
    {
        $latest = null;

        foreach ($guards as $guard) {
            if ($offset > $guard['after']
                && ($guard['before'] === null || $offset <= $guard['before'])
                && ($latest === null || $guard['after'] >= $latest['after'])
            ) {
                $latest = $guard;
            }
        }

        return $latest;
    }

    /**
     * The type of the value a parameter default holds: its constant expression evaluated as PHP evaluates it.
     *
     * @return ValueExpressionResult|null
     */
    private function defaultValue(Expr $default, ExpressionEngine $engine): ?array
    {
        $resolver = new ValueResolver;

        try {
            $value = $resolver->evaluateConstantExpression($default, $this);
        } catch (ConstExprEvaluationException) {
            // A magic constant's value depends on where it appears, so only a bare one binds, and only by its type.
            if ($default instanceof MagicConst) {
                return ['type' => $default instanceof Line ? 'number' : 'string', 'optional' => false];
            }

            // PHP 8.5 allows a static closure or a first-class callable here; json_encode() writes the Closure as `{}`.
            if (($default instanceof ClosureExpr && $default->static)
                || (($default instanceof FuncCall || $default instanceof StaticCall) && $default->isFirstClassCallable())
            ) {
                return ['type' => TsTypeString::EMPTY_OBJECT, 'optional' => false];
            }

            $newDefault = $default instanceof New_ ? $resolver->resolveSerializedNew($default) : null;

            if ($newDefault !== null) {
                return $newDefault;
            }

            // collect() reads the list of a `new Collection([...])` itself, so the list clause below lets it through.
            $readsOwnList = $default instanceof New_ && $resolver->collectCall($default) !== null;

            // Only the engine reads `new`, but it types a closure by its return though json_encode() writes it as `{}`.
            // A list of values the evaluator failed on is left unbound, but a keyed one, whose keys the engine types,
            // still binds even when a value throws, such as `[0 => 1 / 0]`; a variable is no constant expression.
            $unreadable = new NodeFinder()->findFirst($default, fn (Node $node): bool => $node instanceof Variable
                || $node instanceof ClosureExpr
                || $node instanceof ArrowFunction
                || ($node instanceof CallLike && $node->isFirstClassCallable())
                || (! $readsOwnList && $node instanceof Array_ && ! $this->isKeyedLiteral($node)));

            return $unreadable === null ? $engine->resolve($default) : null;
        }

        return $resolver->resolveConstantValue($value);
    }

    /**
     * Whether an array literal keys every item by a literal int or string, so the engine types it by those keys.
     */
    private function isKeyedLiteral(Array_ $array): bool
    {
        return array_all(
            $array->items,
            fn (ArrayItem $item): bool => $item->key instanceof String_ || $item->key instanceof Int_,
        );
    }

    /**
     * The tables that hold a parameter's or a seeded binding, which a body write can end.
     *
     * @return list<array<string, mixed>>
     */
    private function parameterBindingTables(): array
    {
        return [...$this->classBindingTables(), $this->varValueBindings];
    }

    /**
     * The tables that hold a parameter's or a seeded binding other than a plain value.
     *
     * @return list<array<string, mixed>>
     */
    private function classBindingTables(): array
    {
        return [
            $this->varModelBindings,
            $this->varDeclaredBindings,
            $this->varCollectionBindings,
            $this->varClassBindings,
            $this->closureParamExprBindings,
            $this->requestVarNames,
        ];
    }

    /**
     * Drop every parameter name of a closure from every name-keyed binding table.
     *
     * Each reader ranks the tables differently, so an outer binding of the name left in any one of them would outrank
     * the parameter's own binding for some reader: a closure parameter owns its name inside its closure.
     */
    private function releaseParameterNames(ArrowFunction|ClosureExpr $closure): void
    {
        foreach ($closure->params as $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $name = $param->var->name;

            unset(
                $this->closureParamExprBindings[$name],
                $this->varClassBindings[$name],
                $this->varGuardBindings[$name],
                $this->nonNullReads[$name],
                $this->varDocBindings[$name],
                $this->varModelBindings[$name],
                $this->varDeclaredBindings[$name],
                $this->varCollectionBindings[$name],
                $this->varValueBindings[$name],
                $this->localVarBindings[$name],
                $this->byReferenceForms[$name],
                $this->requestVarNames[$name],
                $this->bindingEnds[$name],
                $this->bindingGaps[$name],
                $this->keyBuiltLocals[$name],
                $this->passedValues[$name],
            );
        }
    }

    /**
     * Capture every name table and ambient field within() puts back.
     *
     * @return ScopeState
     */
    private function state(): array
    {
        return [
            'bindings' => $this->nameBindings(),
            'provenProxy' => $this->provenProxy,
            'modelClass' => $this->modelClass,
            'forwardsUndeclaredMembersTo' => $this->forwardsUndeclaredMembersTo,
            'declaringFileClass' => $this->declaringFileClass,
            'declaringClass' => $this->declaringClass,
            'runtimeReflection' => $this->runtimeReflection,
            'resolvingLocalVars' => $this->resolvingLocalVars,
            'readingLocals' => $this->readingLocals,
            'visitedSpreadMethods' => $this->visitedSpreadMethods,
            'narrowedReads' => $this->narrowedReads,
            'appliedProxyProof' => $this->appliedProxyProof,
            'filtersNestedResources' => $this->filtersNestedResources,
            'mapResultArrayed' => $this->mapResultArrayed,
        ];
    }

    /**
     * Put back a state() capture, field by field, never re-deriving one from another.
     *
     * @param  ScopeState  $state
     */
    private function restoreState(array $state): void
    {
        $this->restoreNameBindings($state['bindings']);
        $this->provenProxy = $state['provenProxy'];
        $this->modelClass = $state['modelClass'];
        $this->forwardsUndeclaredMembersTo = $state['forwardsUndeclaredMembersTo'];
        $this->declaringFileClass = $state['declaringFileClass'];
        $this->declaringClass = $state['declaringClass'];
        $this->runtimeReflection = $state['runtimeReflection'];
        $this->resolvingLocalVars = $state['resolvingLocalVars'];
        $this->readingLocals = $state['readingLocals'];
        $this->visitedSpreadMethods = $state['visitedSpreadMethods'];
        $this->narrowedReads = $state['narrowedReads'];
        $this->appliedProxyProof = $state['appliedProxyProof'];
        $this->filtersNestedResources = $state['filtersNestedResources'];
        $this->mapResultArrayed = $state['mapResultArrayed'];
    }
}
