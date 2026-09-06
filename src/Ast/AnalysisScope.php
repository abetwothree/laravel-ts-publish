<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use ReflectionClass;

/**
 * The mutable state threaded through one ResourceAstAnalyzer::analyze() call: the subject under
 * analysis and its backing model, plus the closure/spread bookkeeping that makes local variables,
 * whenLoaded relations, and recursive spreads resolve correctly as traversal descends.
 *
 * @phpstan-type ClosureParamExprBindingsMap array<string, Expr>
 * @phpstan-type VarModelBindingsMap array<string, class-string<Model>>
 * @phpstan-type VarCollectionBindingsMap array<string, array{type: string, modelFqcn: class-string<Model>}>
 * @phpstan-type LocalVarBindingsMap array<string, Expr>
 * @phpstan-type RequestVarNamesMap array<string, class-string<Request>>
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
     * Related model set while analyzing a whenLoaded closure, so `$variable->prop`/`->method()` inside it resolve.
     *
     * @var class-string<Model>|null
     */
    public ?string $closureRelationModelClass = null;

    /**
     * Closure parameter names bound to the `$this->prop` expression found in the surrounding `when()`
     * condition, so `EnumResource::make($status)` resolves like `EnumResource::make($this->status)`.
     *
     * @var ClosureParamExprBindingsMap
     */
    public array $closureParamExprBindings = [];

    /**
     * Closure params / loop vars bound to a model class (whenLoaded params, relation-chain
     * map params, foreach over a many-relation), so `$var`, `$var->prop`, `$var->method()`
     * resolve against that model. Scoped: writers save and restore around the body.
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
     * @param  ReflectionClass<object>  $subjectReflection  the resource (or other AST subject) under analysis
     * @param  class-string<Model>|null  $modelClass  its resolved backing model, if any
     */
    public function __construct(
        public ReflectionClass $subjectReflection,
        public ?string $modelClass = null,
    ) {}
}
