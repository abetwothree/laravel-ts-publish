<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * Laravel's conditional-property family: `when`, `unless`, `whenHas`, `whenNotNull`, `whenNull`,
 * `whenLoaded`, `whenCounted`, `whenAggregated`, `whenPivotLoaded`, `whenPivotLoadedAs`,
 * `whenAppended`, `whenExistsLoaded`, and `transform` — every one of them a `$this->` call.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type NameBindingsSnapshot from AnalysisScope
 *
 * @phpstan-type PassedValue array{value: Expr, resolved: ValueExpressionResult|null, outer: NameBindingsSnapshot}
 *
 * @internal
 */
final class ConditionalMethodHandler implements ExpressionHandler
{
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($this->isThisMethodCall($expr, 'when')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhen($expr, 'when', $scope, $engine);
        }

        // unless() delegates to when() unchanged: negating the condition changes which arm runs,
        // never what either arm's type is.
        if ($this->isThisMethodCall($expr, 'unless')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhen($expr, 'unless', $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenAppended')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenAppended($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenExistsLoaded')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenExistsLoaded($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'transform')) {
            /** @var MethodCall $expr */
            return $this->analyzeTransform($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenHas')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenHas($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenNotNull')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenNotNull($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenNull')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenNull($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenLoaded')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenLoaded($expr, $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenCounted')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenAggregate($expr, 'whenCounted', $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenAggregated')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenAggregate($expr, 'whenAggregated', $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenPivotLoaded')) {
            /** @var MethodCall $expr */
            return $this->applyConditionalDefault(ValueResult::unknown(), $this->arguments($expr, 'whenPivotLoaded'), $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenPivotLoadedAs')) {
            /** @var MethodCall $expr */
            return $this->applyConditionalDefault(ValueResult::unknown(), $this->arguments($expr, 'whenPivotLoadedAs'), $scope, $engine);
        }

        return null;
    }

    /**
     * Fold an explicit default into the value arm: it makes the key required and unions in when it resolves. It is
     * invoked with $defaultArgCount arguments, 0 for value($default) and 1 for transform()'s $default($value), whose
     * one argument is $passedToDefault.
     *
     * @param  ValueExpressionResult  $value
     * @param  PassedValue|null  $passedToDefault
     * @return ValueExpressionResult
     */
    protected function applyConditionalDefault(
        array $value,
        CallArguments $args,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        int $defaultArgCount = 0,
        ?array $passedToDefault = null,
    ): array {
        $defaultArg = $args->named('default');

        if ($defaultArg === null || ! $this->hasExplicitDefaultArg($args)) {
            return [...$value, 'optional' => true];
        }

        $defaultExpr = $defaultArg->value;

        // A default closure requiring more parameters than Laravel supplies it can never run, so its
        // arm is unreachable — the value arm stands alone, still required.
        if ($this->closureRequiresArguments($defaultExpr, $defaultArgCount)) {
            return [...$value, 'optional' => false];
        }

        $default = $this->resolveConditionalDefault($defaultExpr, $passedToDefault, $scope, $engine);

        // An `unknown` on either arm carries no type to union: an unresolved default leaves the value arm
        // standing, and an unresolved value arm already admits whatever the default could produce.
        if ($default['type'] === 'unknown' || $value['type'] === 'unknown') {
            return [...$value, 'optional' => false];
        }

        $members = array_values(array_unique([
            ...TsTypeString::splitTopLevelUnion($value['type']),
            ...TsTypeString::splitTopLevelUnion($default['type']),
        ]));

        // `[]` is assignable to every array type, so an empty-array arm beside a real one would only
        // widen the property into a shape — `Category[] | Record<…>` — that no caller can consume.
        if (array_any($members, fn (string $m): bool => $m !== 'never[]' && str_ends_with($m, '[]'))) {
            $members = array_values(array_filter($members, fn (string $m): bool => $m !== 'never[]'));
        }

        return [...ValueResult::mergeUnion($members, [$value, $default]), 'optional' => false];
    }

    /**
     * Analyze $this->when(condition, value) / $this->unless(condition, value) — the value arm types the key.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhen(MethodCall $call, string $method, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, $method);
        $condition = $args->named('condition');
        $valueArg = $args->named('value');

        if ($condition === null || $valueArg === null) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        $previousNameBindings = $scope->nameBindings();

        try {
            $scope->claimParameters($valueArg->value);
            $this->bindClosureParamsFromCondition($condition->value, $valueArg->value, $scope);
            $scope->bindUnpassedParameters($valueArg->value, 0, $engine);

            $inner = $engine->resolve($valueArg->value);
        } finally {
            $scope->restoreNameBindings($previousNameBindings);
        }

        return $this->applyConditionalDefault($inner, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenHas('attribute') — Laravel returns `value($value, $this->resource->{$attribute})`.
     *
     * A resolvable value argument is therefore what the property carries, with a closure's first
     * parameter bound to the named attribute. The attribute itself answers only when no value can:
     * a skipped one, an EnumResource::make()/::collection() wrap — whose shape decides whether the
     * enum channel is 'enumFqcn' (wrapped — gets the AsEnum rewrite) or 'directEnumFqcn' (read as-is)
     * — or a value the engine cannot type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenHas(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, 'whenHas');
        $attribute = $args->named('attribute')?->value;

        if (! $attribute instanceof String_) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        // `whenHas('attr', default: …)` skips $value: Laravel then evaluates value(null, …), so the present
        // arm is null, never the attribute.
        if ($this->valueSkipped($args)) {
            return $this->applyConditionalDefault(['type' => 'null', 'optional' => false], $args, $scope, $engine);
        }

        $fromValue = $this->resolveValueArgument($args, new PropertyFetch(new Variable('this'), $attribute->value), $scope, $engine);

        if ($fromValue !== null) {
            return $this->applyConditionalDefault($fromValue, $args, $scope, $engine);
        }

        return $this->applyConditionalDefault($this->analyzeAttributeRead($attribute->value, $args, $scope), $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenAppended('attribute', $value, $default) — Laravel returns `value($value)`.
     *
     * A resolvable value types the arm here too, but no attribute binds to a closure parameter: unlike
     * whenHas()/whenExistsLoaded(), whenAppended() forwards none, so a parameter holds its default. The appended
     * accessor answers for a skipped value, an EnumResource::make()/::collection() wrap, and any
     * value the engine cannot type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenAppended(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, 'whenAppended');
        $attribute = $args->named('attribute')?->value;

        if (! $attribute instanceof String_) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        // Same as whenHas(): a skipped $value is value(null) at runtime.
        if ($this->valueSkipped($args)) {
            return $this->applyConditionalDefault(['type' => 'null', 'optional' => false], $args, $scope, $engine);
        }

        $fromValue = $this->resolveValueArgument($args, null, $scope, $engine);

        if ($fromValue !== null) {
            return $this->applyConditionalDefault($fromValue, $args, $scope, $engine);
        }

        return $this->applyConditionalDefault($this->analyzeAttributeRead($attribute->value, $args, $scope), $args, $scope, $engine);
    }

    /**
     * The attribute a whenHas()/whenAppended() call names, typed with every FQCN channel it carries.
     *
     * An EnumResource::make()/::collection() value wraps one enum, so that read puts only the first on `enumFqcn`.
     *
     * @return ValueExpressionResult
     */
    private function analyzeAttributeRead(string $attribute, CallArguments $args, AnalysisScope $scope): array
    {
        $info = $this->resolveModelAttributeTypeInfo($attribute, $scope);
        $result = ['type' => $info['type'], 'optional' => false];
        $valueExpr = $args->named('value')?->value;

        if ($info['enumFqcn'] === null || $valueExpr === null || ! $this->isEnumResourceWrapCall($valueExpr)) {
            return ValueResult::withAttributeChannels($result, $info);
        }

        return [...ValueResult::withAttributeChannels($result, [...$info, 'enumFqcns' => []]), 'enumFqcn' => $info['enumFqcn']];
    }

    /**
     * Whether a whenHas()/whenAppended() value argument is EnumResource::make()/::collection() —
     * including the first-class-callable form — signalling the named attribute is EnumResource-
     * wrapped rather than read directly.
     */
    private function isEnumResourceWrapCall(Expr $value): bool
    {
        if (! $value instanceof StaticCall || ! $value->name instanceof Identifier) {
            return false;
        }

        $className = $this->resolveStaticCallClassName($value);

        return $className !== null
            && $this->isEnumResourceClass($className)
            && in_array($value->name->toString(), ['make', 'collection'], true);
    }

    /**
     * Analyze $this->whenExistsLoaded('relation', $value, $default) — Laravel returns
     * `value($value, $this->resource->{$attribute})`, where $attribute is the relation name snaked and
     * finished with `_exists`.
     *
     * A resolvable value types the arm, with a closure's first parameter bound to that flag; the
     * generated `{relation}_exists` boolean answers when no value can.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenExistsLoaded(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, 'whenExistsLoaded');
        $relationship = $args->named('relationship')?->value;

        if (! $relationship instanceof String_) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        // Unlike whenLoaded(), a skipped $value is not swapped for the identity closure: value(null, …) is null.
        if ($this->valueSkipped($args)) {
            return $this->applyConditionalDefault(['type' => 'null', 'optional' => false], $args, $scope, $engine);
        }

        $flag = new PropertyFetch(new Variable('this'), Str::finish(Str::snake($relationship->value), '_exists'));
        $fromValue = $this->resolveValueArgument($args, $flag, $scope, $engine);

        if ($fromValue !== null) {
            return $this->applyConditionalDefault($fromValue, $args, $scope, $engine);
        }

        return $this->applyConditionalDefault(['type' => 'boolean', 'optional' => false], $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenCounted()/whenAggregated() — a missing or null value publishes the aggregate as `number` by
     * convention, though a driver can return a non-count one as a numeric or date string. A value closure's param
     * binds to `number` for a count, always an integer, and to nothing otherwise; the closure's result types the key.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenAggregate(MethodCall $call, string $method, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, $method);
        $function = $args->named('aggregate')?->value;
        $aggregate = $method === 'whenCounted' || ($function instanceof String_ && $function->value === 'count')
            ? ['type' => 'number', 'optional' => false]
            : ValueResult::unknown();
        $fromValue = $this->resolveValueArgument($args, $aggregate, $scope, $engine);

        return $this->applyConditionalDefault($fromValue ?? ['type' => 'number', 'optional' => false], $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenNotNull($value, $default) — the success arm returns $value, proven non-null.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNotNull(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return $this->analyzeWhenPossiblyNull($call, 'whenNotNull', stripNull: true, scope: $scope, engine: $engine);
    }

    /**
     * Analyze $this->whenNull($value, $default) — the success arm returns null, so only the default
     * carries a useful type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNull(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return $this->analyzeWhenPossiblyNull($call, 'whenNull', stripNull: false, scope: $scope, engine: $engine);
    }

    /**
     * Shared logic for whenNotNull()/whenNull(): argument 0 is the value, argument 1 the optional
     * default. An explicit default makes the key required and unions its type into the result.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenPossiblyNull(MethodCall $call, string $method, bool $stripNull, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, $method);
        $valueArg = $args->named('value');

        if ($valueArg === null) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        $value = $engine->resolve($valueArg->value);

        if ($stripNull) {
            $value['type'] = ValueResult::stripNullArm($value['type']);
        } else {
            $value['type'] = 'null';
        }

        return $this->applyConditionalDefault($value, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenLoaded('relation', value, default): a closure param binds to the relation's model, its whole
     * collection or its morphTo targets, and a variadic one to the list the relation collects into, except a
     * morphTo's, which binds nothing, so its key stays `unknown` and admits the null a relation loaded as null returns.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenLoaded(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = ValueResult::unknown();
        $args = $this->arguments($call, 'whenLoaded');
        $relationship = $args->named('relationship')?->value;
        $valueExpr = $args->named('value')?->value;

        if ($valueExpr !== null) {
            // Resolve the related model so accesses on local variables inside the closure can be typed.
            $previousRelationModel = $scope->closureRelationModelClass;
            $previousNameBindings = $scope->nameBindings();

            try {
                $scope->claimParameters($valueExpr);
                $relationInfo = null;

                if ($relationship instanceof String_) {
                    $relationInfo = $this->resolveModelRelationTypeInfo($relationship->value, $scope);

                    if ($relationInfo['modelFqcn'] !== null) {
                        $scope->closureRelationModelClass = $relationInfo['modelFqcn'];
                    }
                }

                // A variadic parameter collects the relation into a list, so it never holds the relation itself.
                if ($relationInfo !== null && $relationInfo['modelFqcn'] !== null) {
                    $this->bindVariadicList($valueExpr, [
                        'type' => $relationInfo['type'],
                        'optional' => false,
                        'modelFqcn' => $relationInfo['modelFqcn'],
                    ], $scope, $engine);
                }

                if ($relationInfo !== null
                    && $relationInfo['modelFqcn'] !== null
                    && ($valueExpr instanceof ClosureExpr || $valueExpr instanceof ArrowFunction)
                    && isset($valueExpr->params[0])
                    && ! $valueExpr->params[0]->variadic
                    && $valueExpr->params[0]->var instanceof Variable
                    && is_string($valueExpr->params[0]->var->name)
                ) {
                    $paramName = $valueExpr->params[0]->var->name;

                    if (str_ends_with($relationInfo['type'], '[]')) {
                        $scope->varCollectionBindings[$paramName] = [
                            'type' => $relationInfo['type'],
                            'modelFqcn' => $relationInfo['modelFqcn'],
                        ];
                    } else {
                        $scope->varModelBindings[$paramName] = $relationInfo['modelFqcn'];
                    }
                }

                // A morphTo names no single model, so its param holds any one of the targets: bind them all
                // and let each reader union them.
                if ($relationInfo !== null
                    && $relationInfo['modelFqcn'] === null
                    && $relationInfo['morphFqcns'] !== []
                    && ($valueExpr instanceof ClosureExpr || $valueExpr instanceof ArrowFunction)
                    && isset($valueExpr->params[0])
                    && ! $valueExpr->params[0]->variadic
                    && $valueExpr->params[0]->var instanceof Variable
                    && is_string($valueExpr->params[0]->var->name)
                ) {
                    $scope->varClassBindings[$valueExpr->params[0]->var->name] = $relationInfo['morphFqcns'];
                }

                $scope->bindUnpassedParameters($valueExpr, 1, $engine);

                $inner = $engine->resolve($valueExpr);
            } finally {
                $scope->closureRelationModelClass = $previousRelationModel;
                $scope->restoreNameBindings($previousNameBindings);
            }

            return $this->applyConditionalDefault($inner, $args, $scope, $engine);
        }

        // Also `whenLoaded('rel', default: …)`: Laravel then calls value() with a null $value, which it
        // swaps for the identity closure, so the loaded arm is still the relation itself.
        if ($relationship instanceof String_) {
            $info = $this->resolveModelRelationTypeInfo($relationship->value, $scope);
            $result = ['type' => $info['type'], 'optional' => false];

            if ($info['modelFqcn'] !== null) {
                $result['modelFqcn'] = $info['modelFqcn'];
            }

            if ($info['morphFqcns'] !== []) {
                $result['embeddedModelFqcns'] = $info['morphFqcns'];
            }

            return $this->applyConditionalDefault($result, $args, $scope, $engine);
        }

        return [...$result, 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->transform($value, $callback, $default) — types from the callback's return, since
     * transform() invokes $callback with $value rather than passing $value through untouched.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeTransform(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, 'transform');
        $valueArg = $args->named('value');
        $callbackArg = $args->named('callback');

        if ($valueArg === null || $callbackArg === null) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        $previousNameBindings = $scope->nameBindings();

        try {
            // transform() calls $callback($value), so the value is typed before the claim releases a name it may share.
            $value = $valueArg->value;
            $resolvedValue = $engine->resolve($value);

            $scope->claimParameters($callbackArg->value);
            $this->bindPassedValue($callbackArg->value, $value, $resolvedValue, $previousNameBindings, $scope, $engine);
            $scope->bindUnpassedParameters($callbackArg->value, 1, $engine);

            $inner = $engine->resolve($callbackArg->value);
        } finally {
            $scope->restoreNameBindings($previousNameBindings);
        }

        // transform()'s default runs through the global transform() helper's $default($value) — one
        // argument — unlike the rest of the family's zero-argument value($default).
        $passed = ['value' => $value, 'resolved' => $resolvedValue, 'outer' => $previousNameBindings];

        return $this->applyConditionalDefault($inner, $args, $scope, $engine, defaultArgCount: 1, passedToDefault: $passed);
    }

    /**
     * Resolve a conditional default with its parameters bound as Laravel calls it: transform() passes the value, which
     * a blank-value default holds with its null arm, and the rest of the family pass nothing.
     *
     * @param  PassedValue|null  $passed
     * @return ValueExpressionResult
     */
    private function resolveConditionalDefault(Expr $default, ?array $passed, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $previousNameBindings = $scope->nameBindings();

        try {
            $scope->claimParameters($default);

            if ($passed !== null) {
                $this->bindPassedValue($default, $passed['value'], $passed['resolved'], $passed['outer'], $scope, $engine, keepNull: true);
            }

            $scope->bindUnpassedParameters($default, $passed === null ? 0 : 1, $engine);

            return $engine->resolve($default);
        } finally {
            $scope->restoreNameBindings($previousNameBindings);
        }
    }

    /**
     * Whether an explicit default was passed, as Laravel's func_num_args() sees it: `default` counts as
     * passed once passedCount() exceeds its declared position, whether it was written there or by name
     * (a named argument implies every earlier position). A spread makes the count unknowable, so it bails.
     */
    private function hasExplicitDefaultArg(CallArguments $args): bool
    {
        $position = $args->positionOf('default');

        return ! $args->hasUnpack() && $position !== null && $args->passedCount() > $position;
    }

    /**
     * Whether the value argument is skipped (PHP binds it to null but still counts it) or written as a
     * literal null. Either way whenHas()/whenExistsLoaded() evaluate value(null, $attribute) and
     * whenAppended() evaluates value(null) with no extra argument, so the arm is null — none of the three
     * has whenLoaded()'s identity-closure swap. A genuinely absent argument is not this: Laravel's
     * func_num_args() === 1 branch returns the attribute itself, so this must return false for it.
     *
     * The hasUnpack() bail belongs only to the passedCount() branch, which a spread makes unreliable; the
     * literal-null branch reads named('value') alone and needs no such guard.
     */
    private function valueSkipped(CallArguments $args): bool
    {
        $position = $args->positionOf('value');

        if ($position === null) {
            return false;
        }

        $value = $args->named('value');

        if ($value !== null) {
            return $this->isNullConstFetch($value->value);
        }

        return ! $args->hasUnpack() && $args->passedCount() > $position;
    }

    /**
     * The type `value($value, ...$args)` produces, binding a closure's first parameter to $argument (an expression, a
     * typed value, or null for none) and the rest to their defaults; null when there is no usable value, such as none
     * written, a literal null, an EnumResource wrap or an untyped result, so the caller keeps its attribute answer.
     *
     * @param  Expr|ValueExpressionResult|null  $argument
     * @return ValueExpressionResult|null
     */
    private function resolveValueArgument(CallArguments $args, Expr|array|null $argument, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $value = $args->named('value')?->value;

        if ($value === null || $this->isNullConstFetch($value) || $this->isEnumResourceWrapCall($value)) {
            return null;
        }

        $previousNameBindings = $scope->nameBindings();

        try {
            $scope->claimParameters($value);

            if ($argument !== null) {
                $this->bindVariadicList($value, $argument, $scope, $engine);
            }

            // A variadic parameter collects the argument into a list, so it never holds the argument itself.
            if ($argument !== null
                && ($value instanceof ClosureExpr || $value instanceof ArrowFunction)
                && isset($value->params[0])
                && ! $value->params[0]->variadic
                && $value->params[0]->var instanceof Variable
                && is_string($value->params[0]->var->name)
            ) {
                $name = $value->params[0]->var->name;

                if ($argument instanceof Expr) {
                    $scope->closureParamExprBindings[$name] = $argument;
                } elseif ($argument['type'] !== 'unknown') {
                    $scope->varValueBindings[$name] = $argument;
                }
            }

            $scope->bindUnpassedParameters($value, $argument === null ? 0 : 1, $engine);

            $inner = $engine->resolve($value);
        } finally {
            $scope->restoreNameBindings($previousNameBindings);
        }

        return $inner['type'] === 'unknown' ? null : $inner;
    }

    /**
     * The call's arguments mapped against JsonResource's own signature for the method, so every read
     * lands where Laravel binds it whether the caller wrote positions or names.
     */
    private function arguments(MethodCall $call, string $method): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(JsonResource::class, $method));
    }

    /**
     * Bind a required first parameter to the `$this->prop` a `when()` condition tests, so `EnumResource::make($status)`
     * resolves like `EnumResource::make($this->status)`. when() passes the closure nothing, so that call throws; an
     * optional parameter holds its default and a variadic one an empty list, so neither is bound here.
     */
    private function bindClosureParamsFromCondition(Expr $condition, Expr $valueExpr, AnalysisScope $scope): void
    {
        $thisPropExpr = $this->extractThisPropertyFromCondition($condition);

        if ($thisPropExpr === null) {
            return;
        }

        $firstParam = null;

        if ($valueExpr instanceof ArrowFunction && $valueExpr->params !== []) {
            $firstParam = $valueExpr->params[0];
        } elseif ($valueExpr instanceof ClosureExpr && $valueExpr->params !== []) {
            $firstParam = $valueExpr->params[0];
        }

        if ($firstParam === null || $firstParam->variadic || $firstParam->default !== null) {
            return;
        }

        if ($firstParam->var instanceof Variable && is_string($firstParam->var->name)) {
            $scope->closureParamExprBindings[$firstParam->var->name] = $thisPropExpr;
        }
    }

    /**
     * Bind a variadic first parameter to the list Laravel's one argument collects into; any other parameter is left.
     *
     * @param  Expr|ValueExpressionResult  $argument
     */
    private function bindVariadicList(Expr $closure, Expr|array $argument, AnalysisScope $scope, ExpressionEngine $engine): void
    {
        if ((! $closure instanceof ArrowFunction && ! $closure instanceof ClosureExpr)
            || ! isset($closure->params[0])
            || ! $closure->params[0]->variadic
            || ! $closure->params[0]->var instanceof Variable
            || ! is_string($closure->params[0]->var->name)
        ) {
            return;
        }

        $element = $argument instanceof Expr ? $engine->resolve($argument) : $argument;

        if ($element['type'] !== 'unknown') {
            $scope->varValueBindings[$closure->params[0]->var->name] = [
                ...$element,
                'type' => ValueResult::arrayWrapType($element['type']),
                'optional' => false,
            ];
        }
    }

    /**
     * Bind a callback's first parameter to the value its call passes: a `$this->prop` read, every binding the passed
     * variable held before the claim, or the value's own resolved type.
     *
     * @param  ValueExpressionResult|null  $resolvedValue
     * @param  NameBindingsSnapshot  $outer
     */
    private function bindPassedValue(
        Expr $callback,
        Expr $value,
        ?array $resolvedValue,
        array $outer,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        bool $keepNull = false,
    ): void {
        if (! $callback instanceof ArrowFunction && ! $callback instanceof ClosureExpr) {
            return;
        }

        $firstParam = $callback->params[0] ?? null;

        if ($firstParam === null || ! $firstParam->var instanceof Variable || ! is_string($firstParam->var->name)) {
            return;
        }

        // A variadic parameter collects the value into a list, so it never holds the value itself.
        if ($firstParam->variadic) {
            if ($resolvedValue !== null) {
                $element = $keepNull
                    ? $resolvedValue
                    : [...$resolvedValue, 'type' => ValueResult::stripNullArm($resolvedValue['type'])];

                $this->bindVariadicList($callback, $element, $scope, $engine);
            }

            return;
        }

        $name = $firstParam->var->name;

        if ($this->isThisPropertyFetch($value)) {
            $scope->closureParamExprBindings[$name] = $value;

            return;
        }

        if ($value instanceof Variable) {
            if (is_string($value->name)) {
                $scope->copyBindings($value->name, $name, $outer);
            }

            return;
        }

        if ($resolvedValue === null || $resolvedValue['type'] === 'unknown') {
            return;
        }

        // The callback runs only for a filled value, so a nullable model read binds as the model itself; a blank
        // value's default keeps the null arm.
        $model = $resolvedValue['modelFqcn'] ?? null;

        if (! $keepNull
            && $model !== null
            && is_a($model, Model::class, true)
            && ValueResult::stripNullArm($resolvedValue['type']) === class_basename($model)
        ) {
            $scope->varModelBindings[$name] = $model;

            return;
        }

        $scope->varValueBindings[$name] = $resolvedValue;
    }

    /**
     * Extract a `$this->propName` PropertyFetch from a boolean condition, whether used bare as a
     * truthy test or compared identically against null in either operand order.
     */
    private function extractThisPropertyFromCondition(Expr $condition): ?Expr
    {
        if ($this->isThisPropertyFetch($condition)) {
            return $condition;
        }

        if ($condition instanceof BinaryOp\NotIdentical) {
            if ($this->isThisPropertyFetch($condition->left) && $this->isNullConstFetch($condition->right)) {
                return $condition->left;
            }

            if ($this->isThisPropertyFetch($condition->right) && $this->isNullConstFetch($condition->left)) {
                return $condition->right;
            }
        }

        if ($condition instanceof BinaryOp\Identical) {
            if ($this->isThisPropertyFetch($condition->left) && $this->isNullConstFetch($condition->right)) {
                return $condition->left;
            }

            if ($this->isThisPropertyFetch($condition->right) && $this->isNullConstFetch($condition->left)) {
                return $condition->right;
            }
        }

        return null;
    }

    /**
     * Return true when the expression is a `null` constant fetch.
     */
    private function isNullConstFetch(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && strtolower($expr->name->toString()) === 'null';
    }
}
