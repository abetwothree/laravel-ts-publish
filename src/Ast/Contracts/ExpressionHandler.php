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
 * @phpstan-import-type PresenceFacts from MethodAnalysis
 *
 * @phpstan-type HeldNulls = array{objectNull: bool, plainNull: bool}
 * @phpstan-type ValueExpressionResult = array{
 *      type: string,
 *      optional: bool,
 *      absent?: true,
 *      removed?: true,
 *      keyPresence?: array<string, array<string, PresenceFacts>>,
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
 *      unnamedKeys?: bool,
 *      listKeys?: bool,
 *      absentReadsNull?: bool,
 *      absentKeys?: bool,
 *      heldObject?: bool,
 *      mayHoldObject?: HeldNulls,
 *      heldProp?: bool,
 *      returnsCallable?: bool,
 *      closedToDottedKeys?: string,
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
 * `absent` says the value's `optional` is a key its writer may leave unset, not a removed value (MethodAnalysis's
 * presence facts); `removed` beside it says the value may be removed as well, as a printed `?` alone may be.
 *
 * `keyPresence` holds, per object text the value prints, the facts each optional member had where it was written,
 * which a spread of the value reads back; a member with none may be unset or removed alike.
 *
 * `absentKeys` says each optional member of the value's object arms is a key PHP leaves out, never a `MissingValue`,
 * so a read of one throws or gives the member, as preg_match()'s matches leave out the groups that did not take part.
 *
 * `unnamedKeys` says the value is an array literal holding a key its object type leaves out: one read at runtime,
 * spread in, numbered past a keyless item, or written as any expression but a string or an int.
 *
 * `listKeys` says each list arm of the type is keyed by ints, as a list literal, a `list<T>` and a collection's items
 * are; a list without it may be PHPDoc's `T[]` or a native `array`, keyed by anything.
 *
 * `closedToDottedKeys` holds the type of a value Inertia replaces with `[]` before a dotted key writes under it, never
 * opening it as an array: an API resource, an Inertia prop object, a `JsonSerializable` object, any other object a
 * `new` builds, or a callable returning one.
 *
 * A list or collection built from such a value has another type, so ValueResult::closedToDottedKeys() reads the mark
 * only while the type, its null arm aside, is the one it was set for.
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
