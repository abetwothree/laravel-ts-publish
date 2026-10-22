<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ResolvesReturnedValues;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * Expressions rooted at a bound variable rather than `$this` — `$item->name`, `$items->map(…)`,
 * `$items->pluck('x')`, `$item->method()`, and the bare variable itself resolved through an inline `@var` on its
 * assignment, then the scope's model, collection, closure-parameter and local-assignment binding maps.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type VarDocBinding from AnalysisScope
 * @phpstan-import-type BindingGap from AnalysisScope
 *
 * @internal
 */
final class VariableHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use BindsMapCallbacks;
    use CollectsLocalVarBindings;
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use ReadsNonNullGuards;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;
    use SpellsKeyedCollections;

    /** The classes whose map() runs over their items: a collection, or a paginator, which forwards it to its page. */
    private const array MAPS_ITEMS = [Enumerable::class, AbstractPaginator::class, AbstractCursorPaginator::class];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, MethodCall::class, Variable::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $peeled = $expr instanceof MethodCall ? $this->peeledValuesOrAll($expr, $scope, $engine) : null;

        if ($peeled !== null) {
            return $peeled;
        }

        // $variable->property — an attribute of the variable's own bound model (whenLoaded param, map param, foreach
        // value var). Anything else, a relation or a narrowed read included, goes on to ReceiverPropertyFetchHandler.
        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            $boundModel = resolve(ReceiverClassResolver::class)->boundModel($expr->var, $scope);
            $attribute = $boundModel === null ? null : $this->analyzeRelatedModelProperty($expr->name->toString(), $scope, $boundModel);

            if ($attribute !== null && $attribute['type'] !== 'unknown') {
                return $attribute;
            }
        }

        // `$receiver->map(fn (Item $item) => [...])`: the element model comes from the param's type hint, or from the
        // collection the receiver resolver names.
        if ($expr instanceof MethodCall && $this->mappedReceiver($expr) !== null) {
            $mapResult = $this->analyzeReceiverMapCall($expr, $scope, $engine);

            if ($mapResult !== null) {
                return $mapResult;
            }
        }

        // $variable->pluck('field') — an array of the field's type on the element model of the variable's own to-many
        // binding; any other variable goes on to the receiver handlers.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'pluck'
        ) {
            $elementModel = resolve(ReceiverClassResolver::class)->boundElementModel($expr->var, $scope);

            if ($elementModel !== null) {
                return $this->analyzeVariablePluckCall($expr, $scope, $elementModel);
            }
        }

        // $variable->method() — resolve against the variable's own bound model. An only()/except() filter is skipped:
        // the receiver rules own it, and reflecting it here reads Model::except()'s `@return array` as a list or
        // filters a collection by key.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && ! $this->callsAttributeFilter($expr)
        ) {
            $boundModel = resolve(ReceiverClassResolver::class)->boundModel($expr->var, $scope);
            $method = $expr->name->toString();

            // A rule types what reflection reads loosely, and the receiver order reads what it cannot, such as an
            // untyped method's body, so both go on to ReceiverMethodCallHandler.
            if ($boundModel !== null && ! resolve(ReceiverMethodReturnResolver::class)->typesByRule($boundModel, $method)) {
                $result = $this->analyzeRelatedModelMethodCall($method, $boundModel);

                if ($result['type'] !== 'unknown') {
                    return $result;
                }
            }
        }

        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return null;
        }

        $span = $scope->declaredAt($expr);
        $value = $span === null
            ? $this->boundValue($expr, $expr->name, $scope, $engine)
                ?? $this->keyBuiltValue($expr, $expr->name, $scope, $engine)
            : $this->declaredLocalValue($expr->name, $span, $scope, $engine);

        if ($value === null && $this->writtenWhole($expr, $scope)) {
            $value = [...ValueResult::unknown(), 'type' => 'string[]', 'optional' => false];
        }

        $kept = $value === null ? null : $this->keptByReference($expr, $expr->name, $value, $scope, $engine);

        return $kept === null ? null : $this->provenNonNull($expr, $kept, $scope);
    }

    /**
     * A trailing argument-less values()/all() on a collection, typed from the receiver chain, which is the only thing
     * that knows its element: all() hands the array back, keys and all, and values() re-indexes it into a list. On any
     * other class the method is its own, so the receiver's type says nothing about what it returns.
     *
     * @return ValueExpressionResult|null
     */
    private function peeledValuesOrAll(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ((! $call->var instanceof MethodCall && ! $this->isPlainVariable($call->var))
            || ! $call->name instanceof Identifier
            || ! in_array($call->name->toString(), ['values', 'all'], true)
            || $call->isFirstClassCallable()
            || ! CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $call->name->toString()))->isEmpty()
        ) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);

        if (! $this->holdsOnlyCollections($call->var, $receiver, $scope)) {
            return null;
        }

        $method = $call->name->toString();
        $arrayed = $scope->mapResultArrayed;

        try {
            $scope->mapResultArrayed = $arrayed || $method === 'all';
            $receiverResult = $engine->resolve($call->var);
        } finally {
            $scope->mapResultArrayed = $arrayed;
        }

        $type = match (true) {
            $method === 'values' => $this->valuesList($receiverResult['type']),
            $receiver?->keyed === true => $this->keyedAll($receiverResult['type']),
            default => $receiverResult['type'],
        };

        return $type === null || $type === 'unknown' ? null : [...$receiverResult, 'type' => $type];
    }

    /**
     * What all() on a collection whose keys a step broke sends, where its read names only the object: that object, or
     * the `[]` json_encode() writes while it holds nothing, as a `@var Collection<string, X>` local reads.
     */
    private function keyedAll(string $type): string
    {
        $list = count(TsTypeString::splitTopLevelUnion($type)) === 1 && str_starts_with($type, 'Record<')
            ? $this->valuesList($type)
            : null;

        return $list === null ? $type : $this->keyedObjectArm($list);
    }

    /**
     * A bare read's value without its null arm where a guard or a filled-value callback, such as transform()'s, proves
     * the read non-null; a value that is only null keeps it, since nothing else is left to publish.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult
     */
    private function provenNonNull(Variable $read, array $value, AnalysisScope $scope): array
    {
        return $value['type'] !== 'null' && $this->isProvenNonNull($read, $scope) && ValueResult::hasNullArm($value['type'])
            ? [...$value, 'type' => ValueResult::stripNullArm($value['type'])]
            : $value;
    }

    /**
     * A bare variable's value from its model, collection, value, closure-parameter or local-assignment binding, a model
     * as the subclass a guard proves it; past the write that ended its parameter binding, from the local assignment
     * alone, or inside a gap from the value its write assigned, joined with the old binding where that may still hold.
     *
     * @return ValueExpressionResult|null
     */
    private function boundValue(Variable $variable, string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($scope->holdsBinding($variable)) {
            $narrowed = $this->narrowedModel($variable, $name, $scope);

            return $narrowed === null ? $this->heldValue($name, $scope, $engine) : $this->modelValue($narrowed);
        }

        $gap = $scope->gapAt($variable);

        if ($gap === null) {
            $local = $scope->localVarBindings[$name] ?? null;

            return $local === null ? null : $this->boundExpressionValue($name, $local, $scope, $engine);
        }

        return $this->joinedValue($variable, $name, $gap, $scope, $engine);
    }

    /**
     * A read's value inside a gap: the values its writes assigned, joined with the old binding where that may still
     * hold. An arm the engine cannot type is left out beside typed ones, as a ternary leaves one out: a parameter's
     * nested write where an earlier value still reaches the read, and an old value beside writes that all type.
     *
     * @param  BindingGap  $gap
     * @return ValueExpressionResult|null
     */
    private function joinedValue(Variable $read, string $name, array $gap, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // The old binding may still hold past a write nested in a block that falls through, so both are read.
        $written = $gap[2] ?? [];
        $values = $scope->beforeGap($name, $gap, fn (): array => array_map(
            fn (Expr $value): ?array => $this->writtenValue($name, $value, $scope, $engine),
            $written,
        ));
        $untyped = fn (?array $arm): bool => $arm === null || $arm['type'] === 'unknown';
        $nested = $gap[5] ?? [];
        // An earlier value still reaches past a parameter's nested write, so a value it wrote the engine cannot type is
        // left out of the join, as fromGap() leaves a receiver.
        $reaches = $gap[3] || array_any($written, fn (Expr $value): bool => ! in_array($value, $nested, true));
        $old = $gap[3] ? $this->heldValue($name, $scope, $engine) : null;
        $arms = [];
        $dropped = [];

        foreach ($values as $index => $arm) {
            if ($reaches && $untyped($arm) && in_array($written[$index], $nested, true)) {
                $dropped[] = $written[$index];
            } else {
                $arms[] = $arm;
            }
        }

        if ($gap[3] && $untyped($old) && $arms !== [] && ! array_any($arms, $untyped)) {
            $dropped[] = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name]
                ?? $scope->passedValues[$name] ?? $read;
        } elseif ($gap[3]) {
            array_unshift($arms, $old);
        }

        if ($gap[2] === null || $arms === [] || array_any($arms, $untyped)) {
            return null;
        }

        foreach ($dropped as $arm) {
            DroppedUnionArms::record($arm, $scope, 'binding-join');
        }

        $typed = array_values(array_filter($arms));
        $union = count($typed) === 1 ? $typed[0] : ValueResult::unionResults($typed);

        return DroppedUnionArms::warnOfJoinDrop($union, $dropped, $scope);
    }

    /**
     * A local built by key writes, read where no binding types it, as the array its writes before the read build; null
     * for any other variable, and for one whose walk is already under way.
     *
     * @return ValueExpressionResult|null
     */
    private function keyBuiltValue(Variable $read, string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $stmts = $scope->keyBuiltLocals[$name] ?? null;

        if ($stmts === null || ! $engine instanceof ResolvesReturnedValues || isset($scope->resolvingLocalVars[$name])) {
            return null;
        }

        $scope->resolvingLocalVars[$name] = true;

        try {
            return $engine->walkedLocal($read, $stmts);
        } finally {
            unset($scope->resolvingLocalVars[$name]);
        }
    }

    /**
     * A bare variable's value from the binding its name came into the body with, or its local assignment.
     *
     * @return ValueExpressionResult|null
     */
    private function heldValue(string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Bound to a model class (whenLoaded param, map param, foreach value var) — resolves to the model's own type.
        // Checked before closure-param/local-var expression bindings, which resolve through a *different* expression.
        if (isset($scope->varModelBindings[$name])) {
            return $this->modelValue($scope->varModelBindings[$name]);
        }

        // Bound to a whole relation collection (to-many whenLoaded param) — resolves to the collection type, e.g.
        // `User[]`, never the singular element model.
        if (isset($scope->varCollectionBindings[$name])) {
            $binding = $scope->varCollectionBindings[$name];

            return [
                ...ValueResult::unknown(),
                'type' => $binding['type'],
                'optional' => false,
                'modelFqcn' => $binding['modelFqcn'],
            ];
        }

        // Bound to an already-resolved value — a `collect(...)->map()` closure param, whose element type
        // CollectionPipelineHandler resolved before descending into the body.
        if (isset($scope->varValueBindings[$name])) {
            return $scope->varValueBindings[$name];
        }

        // Bound to a closure parameter's expression or a top-level local assignment; the closure param is the narrower
        // scope, and the re-entrancy guard makes a cyclic binding resolve as unknown.
        $boundExpr = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name] ?? null;

        return $boundExpr === null ? null : $this->boundExpressionValue($name, $boundExpr, $scope, $engine);
    }

    /**
     * The subclass of a variable's bound model a guard proves it to hold, where that is one published model, or null.
     *
     * @return class-string<Model>|null
     */
    private function narrowedModel(Variable $variable, string $name, AnalysisScope $scope): ?string
    {
        $bound = $scope->varModelBindings[$name] ?? null;

        if ($bound === null || $scope->guardAt($variable) === null) {
            return null;
        }

        $held = resolve(ReceiverClassResolver::class)->resolve($variable, $scope)->classes ?? [];
        $model = count($held) === 1 ? $held[0] : null;

        return $model !== null
            && is_subclass_of($model, $bound)
            && ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $model])
                ? $model
                : null;
    }

    /**
     * A model's own value, as a bare variable bound to it reads: the model's published type.
     *
     * @param  class-string<Model>  $model
     * @return ValueExpressionResult
     */
    private function modelValue(string $model): array
    {
        return [...ValueResult::unknown(), 'type' => class_basename($model), 'optional' => false, 'modelFqcn' => $model];
    }

    /**
     * A declared local's value: the engine's reading of its annotated assignment's value, unless that is vague or only
     * the `null` an untypable arm's drop left, as AccessorBodyAnalyzer reads a body. Then the `@var` type, if it is
     * precise enough to publish, optional where the reading was, since Laravel drops a missing conditional value.
     *
     * @param  VarDocBinding  $span
     * @return ValueExpressionResult|null
     */
    private function declaredLocalValue(string $name, array $span, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $dropped = DroppedUnionArms::dropped();
        // A placeholder the tag replaces is never published, so the warnings its reading raised are not either.
        $reading = DroppedUnionArms::replaceable(
            fn (): ?array => $this->boundExpressionValue($name, $span['expr'], $scope, $engine),
            fn (?array $reading): bool => $this->declaredReplacement($span, $reading, $dropped) === null,
        );

        return $this->declaredReplacement($span, $reading, $dropped) ?? $reading;
    }

    /**
     * Whether a declared local's reading stands over its `@var`: not vague, and not the `null` an untypable arm's drop
     * left once the read began at the given count of dropped arms.
     *
     * @param  ValueExpressionResult|null  $reading
     */
    private function readingStands(?array $reading, int $dropped): bool
    {
        return $reading !== null
            && ! TsTypeString::isVagueTsType($reading['type'])
            && ($reading['type'] !== 'null' || DroppedUnionArms::dropped() === $dropped);
    }

    /**
     * The `@var` type in place of a reading that does not stand, where the tag is precise enough to publish, optional
     * where the reading was; null where the reading stands or the tag cannot replace it.
     *
     * @param  VarDocBinding  $span
     * @param  ValueExpressionResult|null  $reading
     * @return ValueExpressionResult|null
     */
    private function declaredReplacement(array $span, ?array $reading, int $dropped): ?array
    {
        if ($this->readingStands($reading, $dropped)) {
            return null;
        }

        $declared = resolve(PropertyDocblockTypeReader::class)->readDeclared($span['type'], $span['context']);

        return $declared !== null && ! TsTypeString::isVagueTsType($declared['type']) && ValueResult::namesOnlyPublishedModels($declared)
            ? [...$declared, 'optional' => $reading['optional'] ?? false]
            : null;
    }

    /**
     * A value a gap's write assigned, as the inline `@var` on it declares where it is nested in a block.
     *
     * @return ValueExpressionResult|null
     */
    private function writtenValue(string $name, Expr $value, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $span = $scope->declaredWrite($name, $value);

        return $span === null
            ? $this->boundExpressionValue($name, $value, $scope, $engine)
            : $this->declaredLocalValue($name, $span, $scope, $engine);
    }

    /**
     * Resolve the expression a variable is bound to, or null while that binding is already mid-resolution.
     *
     * @return ValueExpressionResult|null
     */
    private function boundExpressionValue(string $name, Expr $bound, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return $scope->resolvingBinding($name, $bound, fn (): array => $engine->resolve($bound));
    }

    /**
     * A variable's bound value past the by-reference calls before the read that keep it only in some forms, or null, as
     * for a variable written twice, when it lacks one of them. A cyclic read, such as `array_push($a, $a)`, keeps
     * nothing, and a read the source does not place, such as one the engine builds, takes every call's form.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult|null
     */
    private function keptByReference(
        Variable $read,
        string $name,
        array $value,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        $position = $read->getStartFilePos();
        $forms = array_filter(
            $scope->byReferenceForms[$name] ?? [],
            fn (array $form): bool => $position < 0
                || ($position > $form['after'] && ($form['before'] === null || $position <= $form['before'])),
        );

        if ($forms === []) {
            return $value;
        }

        if (isset($scope->resolvingLocalVars[$name])) {
            return null;
        }

        $scope->resolvingLocalVars[$name] = true;
        $type = $value['type'];

        try {
            foreach ($forms as $form) {
                $read = [];
                $written = function (string $element, string $key) use ($form, &$read, $scope, $engine): array {
                    $callback = $form['callback'];
                    $at = $callback === null ? '' : $element."\0".$key;

                    return $read[$at] ??= $callback === null
                        ? array_map(fn (Expr $written): string => $this->writtenType($written, $engine), $form['values'])
                        : $this->writtenByCallback($callback, $form['values'], $element, $key, $scope, $engine);
                };
                $type = $form['effect']->left($type, $written, $form['path'], $scope->reindexesNumericKeys);

                if ($type === null) {
                    return null;
                }
            }
        } finally {
            unset($scope->resolvingLocalVars[$name]);
        }

        return [...$value, 'type' => $type];
    }

    /**
     * Whether a read holds the list of strings preg_match() writes into a name no other write reaches, past a call and
     * no longer holding a binding: only a call that is a statement of the body ends one. A skipped call leaves an
     * unbound name undefined, which Laravel throws on and `??` replaces with its default.
     */
    private function writtenWhole(Variable $read, AnalysisScope $scope): bool
    {
        if (! is_string($read->name) || ($scope->bindsName($read->name) && $scope->holdsBinding($read))) {
            return false;
        }

        $position = $read->getStartFilePos();

        foreach ($scope->byReferenceForms[$read->name] ?? [] as $form) {
            if ($position > $form['after'] && ($form['before'] === null || $position <= $form['before'])) {
                return $form['effect'] === ByReferenceEffect::WritesMatches && $form['sole'] && $form['path'] === [];
            }
        }

        return false;
    }

    /**
     * The types a walk's callback writes into an element of one type under a key of another, each read in the
     * callback's own scope: its first parameter bound to the element and its second to the key, beside what a closure
     * imports, or all an arrow function captures, and the body's own locals. No other outer binding reaches it.
     *
     * @param  list<Expr>  $values
     * @return list<string>
     */
    private function writtenByCallback(
        ArrowFunction|ClosureExpr $callback,
        array $values,
        string $element,
        string $key,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): array {
        $caller = $scope->nameBindings();
        $ambient = $scope->closureRelationModelClass;

        try {
            if ($callback instanceof ClosureExpr) {
                $scope->restoreNameBindings(AnalysisScope::NO_NAME_BINDINGS);
                $scope->closureRelationModelClass = null;

                foreach ($callback->uses as $use) {
                    if (is_string($use->var->name)) {
                        $scope->copyBindings($use->var, $use->var->name, $caller);
                    }
                }
            } else {
                $scope->releaseUnclaimedParameters($callback);
            }

            foreach ([$element, $key] as $index => $type) {
                $parameter = $callback->params[$index] ?? null;

                if ($parameter !== null && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                    $scope->varValueBindings[$parameter->var->name] = ['type' => $type, 'optional' => false];
                }
            }

            if ($callback instanceof ClosureExpr) {
                $this->seedBody($callback->stmts, $scope);
            }

            return array_map(fn (Expr $written): string => $this->writtenType($written, $engine), $values);
        } finally {
            $scope->restoreNameBindings($caller);
            $scope->closureRelationModelClass = $ambient;
        }
    }

    /**
     * The type a write puts in an element: an increment or a decrement leaves a number, any other value its own type.
     */
    private function writtenType(Expr $written, ExpressionEngine $engine): string
    {
        return $written instanceof PreInc || $written instanceof PostInc || $written instanceof PreDec || $written instanceof PostDec
            ? 'number'
            : $engine->resolve($written)['type'];
    }

    /**
     * Analyze `$receiver->map(fn (Item $item) => [...])` with the first param bound to its element: the type hint's
     * model, else the element model or the list element of the collection the receiver resolver names. The body wraps
     * as a list, beside the object arm json_encode() writes where an earlier step broke the receiver's keys.
     *
     * Declines a receiver the resolver names outside MAPS_ITEMS, which runs its own map(), a resource collection's
     * items, an unnamed one but a variable a model hint binds, and a receiver whose element nothing names.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeReceiverMapCall(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $callback = $this->mapArguments($call)->named('callback')?->value;

        if (! $callback instanceof ArrowFunction && ! $callback instanceof ClosureExpr) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);
        $declared = $this->declaredMapModel($callback->params[0] ?? null);

        // A resource collection's items are the API resources it built, or what it was given if it collects none.
        if ($receiver === null
            ? ! $this->mayHoldCollection($call->var, $declared, $scope)
            : ! $receiver->within(self::MAPS_ITEMS) || $receiver->collectedItems
        ) {
            return null;
        }

        $elementModel = $receiver?->elementModel;
        $elementValue = null;

        // A collection of plain values, such as a pluck() or an earlier map(), passes each value its list holds.
        if ($declared === null && $elementModel === null) {
            $elementValue = $receiver === null ? null : $this->listElement($engine->resolve($call->var));

            if ($elementValue === null) {
                return null;
            }
        }

        $bodyResult = $this->resolveMapCallback(
            $callback,
            $elementModel,
            $elementValue,
            $scope->mapResultArrayed,
            $this->mapsAsRelationClosure($call->var, $declared, $scope),
            $scope,
            $engine,
        );

        if ($bodyResult === null || $bodyResult['type'] === 'unknown') {
            return null;
        }

        // ValueResult::arrayWrapType(), not a raw '[]' suffix: a union body (e.g. a mixed AsEnum/direct-enum
        // ternary) must be parenthesized before the array suffix binds.
        $list = ValueResult::arrayWrapType($bodyResult['type']);
        $type = $receiver?->keyed === true ? $this->keyedObjectArm($list) : $list;

        // A `?->` earlier in the receiver can end the whole chain as null before map() runs.
        return [...$bodyResult, 'type' => $receiver?->shortCircuits === true ? ValueResult::withNullArm($type) : $type, 'optional' => false];
    }

    /**
     * Whether a map's body counts as a relation closure: one over a to-many whenLoaded() parameter's collection, or
     * over any variable with a callback that declares the model it maps.
     *
     * @param  class-string<Model>|null  $declared
     */
    private function mapsAsRelationClosure(Expr $receiver, ?string $declared, AnalysisScope $scope): bool
    {
        return $receiver instanceof Variable
            && $receiver->name !== 'this'
            && ($declared !== null || resolve(ReceiverClassResolver::class)->boundElementModel($receiver, $scope) !== null);
    }

    /**
     * The value each element of a one-armed list type holds, with the channels the list carries; else null.
     *
     * @param  ValueExpressionResult  $list
     * @return ValueExpressionResult|null
     */
    private function listElement(array $list): ?array
    {
        $element = count(TsTypeString::splitTopLevelUnion($list['type'])) === 1 ? TsTypeShape::elementType($list['type']) : null;

        return $element === null || $element === 'unknown' || $element === 'never'
            ? null
            : [...$list, 'type' => $element, 'optional' => false];
    }

    /**
     * Whether an unnamed receiver is read as a collection: a variable a model hint binds the element of, unless its one
     * write is an array literal, on which map() throws.
     *
     * @param  class-string<Model>|null  $declared
     */
    private function mayHoldCollection(Expr $receiver, ?string $declared, AnalysisScope $scope): bool
    {
        return $declared !== null
            && $receiver instanceof Variable
            && is_string($receiver->name)
            && $receiver->name !== 'this'
            && ! (($scope->literalLocalBindings()[$receiver->name] ?? null) instanceof Array_);
    }

    /**
     * A `$variable->map(...)` call's arguments mapped against Collection::map(callable $callback).
     */
    private function mapArguments(MethodCall $call): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'map'));
    }

    /**
     * Whether an expression, which the receiver resolver reads as $receiver, holds only Illuminate collections, whose
     * values() and all() the engine knows.
     */
    private function holdsOnlyCollections(Expr $expr, ?ReceiverType $receiver, AnalysisScope $scope): bool
    {
        // An unnamed receiver passes only as the map arm's `$variable->map(callback)` over an unnamed variable,
        // which that arm already types as a Collection map; values() leaves it one.
        if ($receiver === null) {
            while ($expr instanceof MethodCall
                && $expr->name instanceof Identifier
                && $expr->name->toString() === 'values'
                && ! $expr->isFirstClassCallable()
                && $expr->getArgs() === []
            ) {
                $expr = $expr->var;
            }

            $mapped = $this->mappedReceiver($expr);

            return $mapped instanceof Variable && resolve(ReceiverClassResolver::class)->resolve($mapped, $scope) === null;
        }

        // The receiver rules type values()/all() on a variable whose element the resolver names, keys included, where
        // its bare read may give only its `@var` type.
        if ($expr instanceof Variable && $receiver->elementModel !== null) {
            return false;
        }

        return array_all($receiver->classes, fn (string $class): bool => is_a($class, Enumerable::class, true));
    }

    /**
     * The receiver a `$receiver->map(callback)` call maps over, but a bare `$this`, else null.
     */
    private function mappedReceiver(Expr $expr): ?Expr
    {
        return $expr instanceof MethodCall
            && ! ($expr->var instanceof Variable && $expr->var->name === 'this')
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'map'
            && ! $this->mapArguments($expr)->isEmpty()
                ? $expr->var
                : null;
    }

    /**
     * Whether an expression is a variable other than `$this`.
     */
    private function isPlainVariable(Expr $expr): bool
    {
        return $expr instanceof Variable && is_string($expr->name) && $expr->name !== 'this';
    }
}
