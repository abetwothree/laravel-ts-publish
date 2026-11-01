<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;

/**
 * Claims a set of php-parser Expr node classes and resolves a matched expression to its TypeScript
 * type, or declines so the dispatcher tries the next handler. See ExpressionDispatcher.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type CarriedMap from MethodAnalysis
 *
 * @phpstan-type HeldNulls = array{objectNull: bool, plainNull: bool}
 * @phpstan-type ValueExpressionResult = array{
 *      type: string,
 *      optional: bool,
 *      enumFqcn?: class-string,
 *      directEnumFqcn?: class-string,
 *      resourceFqcn?: class-string,
 *      modelFqcn?: class-string,
 *      embeddedEnumFqcns?: list<class-string>,
 *      embeddedEnumResourceFqcns?: list<class-string>,
 *      embeddedModelFqcns?: list<class-string>,
 *      embeddedResourceFqcns?: list<class-string>,
 *      multiEnumResourceFqcns?: list<class-string>,
 *      customImports?: TypesImportMap,
 *      carriedFqcns?: CarriedMap,
 *      wrapIsCollection?: bool,
 *      directIsArray?: bool,
 *      keepsKeys?: bool,
 *      absentReadsNull?: bool,
 *      heldObject?: bool,
 *      mayHoldObject?: HeldNulls,
 *      heldProp?: bool,
 *      returnsCallable?: bool,
 *      closedToDottedKeys?: bool,
 *      resolvedFqcn?: class-string<JsonResource>
 * }
 *
 * `directEnumFqcn` says the value holds this enum, and its type may spell the enum's override instead of its
 * `…Type`, so test ValueResult::readsEnumType() before treating the enum's name as spelled. `resolvedFqcn` says the
 * value is the keys that resource's `resolve()` returns, typed as Inertia sends them, so a spread takes its keys.
 *
 * `heldObject` says PHP holds the value as an object, a closure or an Inertia prop, never null until the caller
 * resolves it to the type, so a `??=` of it never runs (KeepsHeldObjects); `mayHoldObject` says only some arms do, and
 * whether an object arm may resolve to null and a plain arm may be null, which only the plain one `??=` replaces.
 *
 * `heldProp` says every such object is an Inertia prop, which Inertia resolves even where a callable it called returned
 * it, unlike a callable; `returnsCallable` says a callable's call may return a callable.
 *
 * `closedToDottedKeys` says Inertia replaces the value with `[]` before a dotted key writes under it, never opening it
 * as an array: an API resource it sends as a response, an Inertia prop object, or a callable returning either.
 *
 * @internal
 */
interface ExpressionHandler
{
    /**
     * The Expr classes (concrete or abstract) this handler is a candidate for.
     *
     * @return list<class-string<Expr>>
     */
    public function nodeClasses(): array;

    /**
     * Resolve a claimed expression, or return null to decline and fall through to the next handler.
     *
     * @return ValueExpressionResult|null
     */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array;
}
