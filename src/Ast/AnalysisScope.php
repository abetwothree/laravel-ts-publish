<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The mutable state threaded through one ResourceAstAnalyzer::analyze() call: the subject under
 * analysis and its backing model, plus the closure/spread bookkeeping that makes local variables,
 * whenLoaded relations, and recursive spreads resolve correctly as traversal descends.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ClosureParamExprBindingsMap array<string, Expr>
 * @phpstan-type VarClassBindingsMap array<string, non-empty-list<class-string>>
 * @phpstan-type VarGuardBindingsMap array<string, array{classes: non-empty-list<class-string>, after: int}>
 * @phpstan-type VarDocBinding array{type: string, context: ReflectionClass<object>, after: int, before: int|null}
 * @phpstan-type VarDocBindingsMap array<string, non-empty-list<VarDocBinding>>
 * @phpstan-type VarModelBindingsMap array<string, class-string<Model>>
 * @phpstan-type VarCollectionBindingsMap array<string, array{type: string, modelFqcn: class-string<Model>}>
 * @phpstan-type VarValueBindingsMap array<string, ValueExpressionResult>
 * @phpstan-type LocalVarBindingsMap array<string, Expr>
 * @phpstan-type RequestVarNamesMap array<string, class-string<Request>>
 * @phpstan-type NameBindingsSnapshot array{
 *      closureParamExprBindings: ClosureParamExprBindingsMap,
 *      varClassBindings: VarClassBindingsMap,
 *      varGuardBindings: VarGuardBindingsMap,
 *      varDocBindings: VarDocBindingsMap,
 *      varModelBindings: VarModelBindingsMap,
 *      varCollectionBindings: VarCollectionBindingsMap,
 *      varValueBindings: VarValueBindingsMap,
 *      localVarBindings: LocalVarBindingsMap,
 *      requestVarNames: RequestVarNamesMap,
 *      claimedClosures: array<int, true>
 * }
 *
 * @internal
 */
final class AnalysisScope
{
    /**
     * Wrapped class from an `instanceof` guard in toArray(); fallback when resolveClassOnProperty() returns null.
     *
     * @var class-string|null
     */
    public ?string $instanceOfWrappedClass = null;

    /**
     * The class an undeclared `$this->member` read or call forwards to (a JsonResource proxies both to
     * `$this->resource`), or null. Derived in the constructor so every scope carries it; ResourceAstAnalyzer re-derives
     * it, TernaryHandler narrows it, and ReceiverClassResolver reads it rather than testing for JsonResource.
     *
     * @var class-string|null
     */
    public ?string $forwardsUndeclaredMembersTo = null;

    /**
     * False while MethodReturnTypeResolver's body fallback analyzes a method: it flattens the shape into a type string
     * with no FQCN channel and drops the whole shape once a value names a token. Filter code reads it to publish the
     * most specific answer that names none.
     */
    public bool $carriesImports = true;

    /**
     * Related model set while analyzing a whenLoaded closure, so `$variable->prop`/`->method()` inside it resolve.
     *
     * @var class-string<Model>|null
     */
    public ?string $closureRelationModelClass = null;

    /**
     * Closure parameter names bound to an expression: a required `when()` parameter to its condition's `$this->prop`,
     * so `EnumResource::make($status)` resolves like `EnumResource::make($this->status)`; a `transform()` callback's
     * to the `$this->prop` it is passed; and a `whenHas()`/`whenExistsLoaded()` value closure's to the attribute read.
     *
     * @var ClosureParamExprBindingsMap
     */
    public array $closureParamExprBindings = [];

    /**
     * Variables a ternary's `instanceof` test, or a morphTo `whenLoaded()` parameter's targets, prove to hold one of
     * some classes. Read before varModelBindings, which a narrowed variable usually also has and which would otherwise
     * win. Scoped: writers save and restore around the body.
     *
     * @var VarClassBindingsMap
     */
    public array $varClassBindings = [];

    /**
     * Variables an early-exit `instanceof` guard proves to hold a class, with the file offset the guard's `if` ends at:
     * the proof holds only for a read past that offset. Read right after varClassBindings, and scoped like it.
     *
     * @var VarGuardBindingsMap
     */
    public array $varGuardBindings = [];

    /**
     * Variables an inline `@var` on their assignment declares a type for: the type as written, the class whose file
     * resolves its names, and the reads it holds for, past the assignment and before the top-level statement that next
     * writes the variable. Written by CollectsLocalVarBindings, and scoped like localVarBindings.
     *
     * @var VarDocBindingsMap
     */
    public array $varDocBindings = [];

    /**
     * Names bound to a model class, so `$var`, `$var->prop`, `$var->method()` resolve against it: a to-one whenLoaded,
     * map or transform() closure param, scoped to its closure's body, and, method-wide, a foreach variable over a
     * many-relation or a `Model`-typed parameter AstEngine::bindingsFor() seeds.
     *
     * @var VarModelBindingsMap
     */
    public array $varModelBindings = [];

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
     * Re-entrancy guard: variable names currently mid-resolution, so a self- or mutually-referential
     * binding (e.g. `$a = $b; $b = $a;`) resolves as unknown instead of recursing forever.
     *
     * @var array<string, true>
     */
    public array $resolvingLocalVars = [];

    /**
     * Spread methods currently on the analysis stack, so a method that spreads itself — directly or
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

        // The subject alone decides this, so every scope carries it without the builder having to remember;
        // ResourceAstAnalyzer re-derives it once an instanceof guard supplies a backing the constructor lacked.
        $this->forwardsUndeclaredMembersTo = $modelClass !== null && $subjectReflection->isSubclassOf(JsonResource::class)
            ? $modelClass
            : null;
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
            'varDocBindings' => $this->varDocBindings,
            'varModelBindings' => $this->varModelBindings,
            'varCollectionBindings' => $this->varCollectionBindings,
            'varValueBindings' => $this->varValueBindings,
            'localVarBindings' => $this->localVarBindings,
            'requestVarNames' => $this->requestVarNames,
            'claimedClosures' => $this->claimedClosures,
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
        $this->varDocBindings = $snapshot['varDocBindings'];
        $this->varModelBindings = $snapshot['varModelBindings'];
        $this->varCollectionBindings = $snapshot['varCollectionBindings'];
        $this->varValueBindings = $snapshot['varValueBindings'];
        $this->localVarBindings = $snapshot['localVarBindings'];
        $this->requestVarNames = $snapshot['requestVarNames'];
        $this->claimedClosures = $snapshot['claimedClosures'];
    }

    /**
     * Release a closure's parameter names before a writer binds them, marked so ClosureHandler keeps those bindings.
     */
    public function claimParameters(Expr $closure): void
    {
        if ($closure instanceof ArrowFunction || $closure instanceof Closure) {
            $this->releaseParameterNames($closure);
            $this->claimedClosures[spl_object_id($closure)] = true;
        }
    }

    /**
     * Release the parameter names of a closure no writer claimed, as ClosureHandler does before analyzing its body.
     */
    public function releaseUnclaimedParameters(Expr $closure): void
    {
        if (! $closure instanceof ArrowFunction && ! $closure instanceof Closure) {
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
        if (! $closure instanceof ArrowFunction && ! $closure instanceof Closure) {
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
        }
    }

    /**
     * Bind a claimed parameter to every entry the variable its call passes held in a nameBindings() capture.
     *
     * @param  NameBindingsSnapshot  $snapshot
     */
    public function copyBindings(string $from, string $to, array $snapshot): void
    {
        if (isset($snapshot['closureParamExprBindings'][$from])) {
            $this->closureParamExprBindings[$to] = $snapshot['closureParamExprBindings'][$from];
        }

        if (isset($snapshot['varClassBindings'][$from])) {
            $this->varClassBindings[$to] = $snapshot['varClassBindings'][$from];
        }

        if (isset($snapshot['varGuardBindings'][$from])) {
            $this->varGuardBindings[$to] = $snapshot['varGuardBindings'][$from];
        }

        if (isset($snapshot['varDocBindings'][$from])) {
            $this->varDocBindings[$to] = $snapshot['varDocBindings'][$from];
        }

        if (isset($snapshot['varModelBindings'][$from])) {
            $this->varModelBindings[$to] = $snapshot['varModelBindings'][$from];
        }

        if (isset($snapshot['varCollectionBindings'][$from])) {
            $this->varCollectionBindings[$to] = $snapshot['varCollectionBindings'][$from];
        }

        if (isset($snapshot['varValueBindings'][$from])) {
            $this->varValueBindings[$to] = $snapshot['varValueBindings'][$from];
        }

        if (isset($snapshot['localVarBindings'][$from])) {
            $this->localVarBindings[$to] = $snapshot['localVarBindings'][$from];
        }

        if (isset($snapshot['requestVarNames'][$from])) {
            $this->requestVarNames[$to] = $snapshot['requestVarNames'][$from];
        }
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

            $stringDefault = $default instanceof New_ ? $resolver->resolveStringSerializedNew($default) : null;

            if ($stringDefault !== null) {
                return $stringDefault;
            }

            // Only the engine reads `new`, but it types a list literal as a record and a closure by its return, though
            // json_encode() writes a Closure as `{}`; a default that reads a variable is no constant expression at all.
            $unreadable = new NodeFinder()->findFirst($default, fn (Node $node): bool => $node instanceof Variable
                || $node instanceof Closure
                || $node instanceof ArrowFunction
                || ($node instanceof CallLike && $node->isFirstClassCallable())
                || ($node instanceof Array_ && ! $this->isRecordLiteral($node)));

            return $unreadable === null ? $engine->resolve($default) : null;
        }

        return $resolver->resolveConstantValue($value, $engine);
    }

    /**
     * Whether an array literal keys every item by a string that fails is_numeric(), so no resource re-indexes it.
     */
    private function isRecordLiteral(Array_ $array): bool
    {
        return array_all($array->items, fn (ArrayItem $item): bool => $item->key instanceof String_
            && ! is_numeric($item->key->value));
    }

    /**
     * Drop every parameter name of a closure from every name-keyed binding table.
     *
     * Each reader ranks the tables differently, so an outer binding of the name left in any one of them would outrank
     * the parameter's own binding for some reader: a closure parameter owns its name inside its closure.
     */
    private function releaseParameterNames(ArrowFunction|Closure $closure): void
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
                $this->varDocBindings[$name],
                $this->varModelBindings[$name],
                $this->varCollectionBindings[$name],
                $this->varValueBindings[$name],
                $this->localVarBindings[$name],
                $this->requestVarNames[$name],
            );
        }
    }
}
