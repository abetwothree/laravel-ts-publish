<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\AggregateValueType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\TypeScriptMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
    use ReadsNonNullGuards;
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
     * Fold an explicit default into the value arm: it makes the key required, unless either arm can itself be a
     * MissingValue, and unions in when it resolves. It is invoked with $defaultArgCount arguments, 0 for
     * value($default) and 1 for transform()'s $default($value), whose one argument is $passedToDefault.
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
            return ValueResult::missable($value);
        }

        $defaultExpr = $defaultArg->value;

        // A default closure requiring more parameters than Laravel supplies it can never run, so its
        // arm is unreachable — the value arm stands alone, optional only as its own value is.
        if ($this->closureRequiresArguments($defaultExpr, $defaultArgCount)) {
            return $value;
        }

        $default = $this->resolveConditionalDefault($defaultExpr, $passedToDefault, $scope, $engine);

        // An `unknown` on either arm carries no type to union: an unresolved default leaves the value arm
        // standing, and an unresolved value arm already admits whatever the default could produce.
        if ($default['type'] === 'unknown' || $value['type'] === 'unknown') {
            // Only a value arm with a type publishes without the default, so only then is an arm left out.
            if ($value['type'] !== 'unknown') {
                DroppedUnionArms::record($defaultExpr, $scope, 'conditional-default');
                DroppedUnionArms::warnOfPlaceholder($value, [$defaultExpr], $scope);
            }

            return [...$value, 'optional' => $value['optional'] || $default['optional']];
        }

        $valueMembers = TsTypeString::splitTopLevelUnion($value['type']);
        $defaultMembers = TsTypeString::splitTopLevelUnion($default['type']);

        // `[]` is assignable to every array type, so an empty-array arm beside a real one would only
        // widen the property into a shape — `Category[] | Record<…>` — that no caller can consume. It is taken
        // out of each arm, since unionResults() reads the members arm by arm where two classes share a name.
        if (array_any([...$valueMembers, ...$defaultMembers], fn (string $m): bool => $m !== 'never[]' && str_ends_with($m, '[]'))) {
            $value['type'] = implode(' | ', array_diff($valueMembers, ['never[]']));
            $default['type'] = implode(' | ', array_diff($defaultMembers, ['never[]']));
        }

        // Count members, not arms: a `null` then keeps two enum resources from reading as a union of enum resources,
        // which an inline array's member and Inertia shared data do not honour.
        return ValueResult::unionResults([$value, $default], countMembers: true);
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

        if ($this->closureRequiresArguments($valueArg->value)) {
            $this->warnOfUnpassedArgument($method, $valueArg->value, $scope);
        }

        $inner = $scope->readClaimedClosure(
            $valueArg->value,
            0,
            $engine,
            fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall(FirstClassCallableHandler::heldCallable($valueArg->value, $scope))),
            bind: fn () => $this->bindClosureParamsFromCondition($condition->value, $valueArg->value, $scope),
            // The value runs only where when()'s condition holds, or unless()'s fails. Proven before the claim, so a
            // closure parameter named like a proven read drops its proof.
            beforeClaim: fn () => $this->proveNonNull($this->nonNullReads($condition->value, $method === 'when'), $scope),
        );

        return $this->applyConditionalDefault($inner, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenHas('attribute'): Laravel returns `value($value, $this->resource->{$attribute})`, so
     * the value, `unknown` included, is what the property carries, with a closure's first parameter bound to the
     * attribute. The attribute answers only for a value-less call or an EnumResource::make()/::collection() wrap.
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
     * Analyze $this->whenAppended('attribute', $value, $default): Laravel returns `value($value)`, so the value types
     * the arm, `unknown` included, and a closure parameter holds its default. The appended accessor answers only for a
     * value-less call or an EnumResource::make()/::collection() wrap.
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
     * Analyze $this->whenExistsLoaded('relation', $value, $default): Laravel returns `value($value, $flag)`, where
     * $flag is the snaked relation's `_exists` attribute, so a resolvable value types the arm with a closure's first
     * parameter bound to that flag; the generated `{relation}_exists` boolean answers when no value can.
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

        if ($fromValue !== null && $fromValue['type'] !== 'unknown') {
            return $this->applyConditionalDefault($fromValue, $args, $scope, $engine);
        }

        return $this->applyConditionalDefault(['type' => 'boolean', 'optional' => false], $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenCounted()/whenAggregated(): a missing or null value publishes the aggregate, and a value
     * closure's result types the key, its first parameter bound to the aggregate without its null. An untypable closure
     * publishes the aggregate; an aggregate nothing types publishes `number`.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenAggregate(MethodCall $call, string $method, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, $method);
        $functionArg = $args->named('aggregate')?->value;
        $function = $method === 'whenCounted' ? 'count' : ($functionArg instanceof String_ ? $functionArg->value : null);
        $aggregate = $this->typedAggregate($function, $args, $scope);
        $passed = $aggregate === null
            ? ValueResult::unknown()
            : [...$aggregate, 'type' => ValueResult::stripNullArm($aggregate['type'])];
        $fromValue = $this->resolveValueArgument($args, $passed, $scope, $engine);
        $typed = $fromValue !== null && $fromValue['type'] !== 'unknown' ? $fromValue : null;
        $value = $typed ?? $aggregate ?? ['type' => 'number', 'optional' => false];

        // Any aggregate but a count is SQL NULL over no rows, which Laravel returns before it calls the closure. Only
        // the model's own declaration can turn that NULL into a value.
        if ($function !== null && $function !== 'count'
            && ($aggregate === null || ValueResult::hasNullArm($aggregate['type']))
        ) {
            $value['type'] = ValueResult::withNullArm($value['type']);
        }

        return $this->applyConditionalDefault($value, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenNotNull($value, $default): the success arm returns $value proven non-null, or, for a closure
     * or first-class callable, which is never null, what it returns, null included.
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
     * default. An explicit default makes the key required and unions its type into the result, but for a whenNotNull()
     * callable, which always runs.
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

        $held = FirstClassCallableHandler::heldCallable($valueArg->value, $scope);
        $called = $stripNull ? $this->calledWithNothing($held, $scope, $engine) : null;

        if ($called !== null) {
            return $called;
        }

        $value = $engine->resolve(FirstClassCallableHandler::invokedCall($held));

        if ($stripNull) {
            $value['type'] = ValueResult::stripNullArm($value['type']);
        } else {
            // whenNull() returns its value only when that value is null, so the arm names nothing the value does. The
            // value is still resolved, so that an arm dropped inside it is counted.
            $value = ['type' => 'null', 'optional' => false];
        }

        return $this->applyConditionalDefault($value, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenLoaded('relation', value, default): a closure param binds to the relation's model, its whole
     * collection or its morphTo targets, and a variadic one to the list the relation collects into. A value arm also
     * takes the `null` Laravel returns, before it reads the value, for a relation that can be loaded as null.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenLoaded(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = ValueResult::unknown();
        $args = $this->arguments($call, 'whenLoaded');
        $relationship = $args->named('relationship')?->value;
        $valueExpr = $args->named('value')?->value;

        if ($valueExpr !== null && ! $this->isNullConstFetch($valueExpr)) {
            // Laravel calls a callable with the loaded relation, so a first-class one binds it as a closure does.
            $valueExpr = FirstClassCallableHandler::passingOneValue(FirstClassCallableHandler::heldCallable($valueExpr, $scope));
            $inner = $scope->readClaimedClosure(
                $valueExpr,
                1,
                $engine,
                fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall($valueExpr)),
                fn () => $this->bindLoadedRelation($valueExpr, $relationship, $scope, $engine),
            );

            $loaded = $this->withLoadedNullArm($inner, $relationship, $valueExpr, $scope);

            return $this->applyConditionalDefault($loaded, $args, $scope, $engine);
        }

        // Also `whenLoaded('rel', null, …)` and `whenLoaded('rel', default: …)`: Laravel swaps the null $value for the
        // identity closure, so the loaded arm is still the relation itself.
        $info = $relationship instanceof String_ ? $this->resolveModelRelationTypeInfo($relationship->value, $scope) : null;

        // A relation that does not type, or a name the engine cannot read, would widen the key to `unknown`, so a
        // literal null value keeps its own type.
        if ($valueExpr !== null && ($info === null || $info['type'] === 'unknown')) {
            return $this->applyConditionalDefault(['type' => 'null', 'optional' => false], $args, $scope, $engine);
        }

        if ($info !== null) {
            $result = ['type' => $info['type'], 'optional' => false];

            if ($info['modelFqcn'] !== null) {
                $result['modelFqcn'] = $info['modelFqcn'];
            }

            if ($info['morphFqcns'] !== []) {
                $result['embeddedModelFqcns'] = $info['morphFqcns'];
            }

            return $this->applyConditionalDefault($result, $args, $scope, $engine);
        }

        return ValueResult::missable($result); // @codeCoverageIgnore
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

        $value = $valueArg->value;
        $callback = FirstClassCallableHandler::passingOneValue(FirstClassCallableHandler::heldCallable($callbackArg->value, $scope));
        $resolvedValue = null;
        $model = null;
        $outer = AnalysisScope::NO_NAME_BINDINGS;

        $inner = $scope->readClaimedClosure(
            $callback,
            1,
            $engine,
            function () use ($callback, $value, $scope, $engine): array {
                // transform() calls back only for a filled value, so the read it passes is not null in the callback.
                $this->proveNonNull([$value], $scope);

                return $engine->resolve($callback);
            },
            function () use ($callback, $value, &$resolvedValue, &$outer, $scope, $engine, &$model): void {
                $this->bindPassedValue($callback, $value, $resolvedValue, $outer, $scope, $engine, model: $model);
            },
            // transform() calls $callback($value), so the value is typed before the claim releases a name it may share.
            function () use ($value, &$resolvedValue, &$model, &$outer, $scope, $engine): void {
                $resolvedValue = $engine->resolve($value);
                $model = $this->filledModel($value, $resolvedValue, $scope);
                $outer = $scope->nameBindings();
            },
        );

        // transform()'s default runs through the global transform() helper's $default($value) — one
        // argument — unlike the rest of the family's zero-argument value($default).
        $passed = ['value' => $value, 'resolved' => $resolvedValue, 'outer' => $outer];

        return $this->applyConditionalDefault($inner, $args, $scope, $engine, defaultArgCount: 1, passedToDefault: $passed);
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
     * The value arm with the `null` whenLoaded() returns, without reading the value, for a relation loaded as null. A
     * relation the model does not declare holds whatever setRelation() stored, so only relationLoadsNull()'s `false`
     * rules the arm out, or a value that collects the relation, which then is a to-many one.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult
     */
    private function withLoadedNullArm(array $value, ?Expr $relationship, Expr $valueExpr, AnalysisScope $scope): array
    {
        if (! $relationship instanceof String_) {
            return $value;
        }

        $loadsNull = $this->relationLoadsNull($relationship->value, $scope);

        if ($loadsNull === false || ($loadsNull === null && $this->collectsLoadedValue($valueExpr, $scope))) {
            return $value;
        }

        return [...$value, 'type' => ValueResult::withNullArm($value['type'])];
    }

    /**
     * Whether a whenLoaded() value hands the loaded relation to a resource's `collection()`: a closure whose one return
     * is `X::collection($param)` on its first parameter, which passingOneValue() makes of `X::collection(...)` too.
     */
    private function collectsLoadedValue(Expr $value, AnalysisScope $scope): bool
    {
        $param = $value instanceof ArrowFunction || $value instanceof ClosureExpr ? ($value->params[0] ?? null) : null;

        if ($param === null || $param->variadic || ! $param->var instanceof Variable) {
            return false;
        }

        $returns = $this->resolveClosureReturnExpressions($value);
        $call = count($returns) === 1 ? $returns[0] : null;

        if (! $call instanceof StaticCall || $call->isFirstClassCallable() || ! $this->isResourceCollectionCall($call, $scope)) {
            return false;
        }

        $passed = $call->getArgs()[0] ?? null;

        return $passed !== null && ! $passed->unpack && $passed->value instanceof Variable && $passed->value->name === $param->var->name;
    }

    /**
     * Whether a static call is `collection()` on an API resource class, `self` and `static` naming the subject.
     */
    private function isResourceCollectionCall(StaticCall $call, AnalysisScope $scope): bool
    {
        return $call->name instanceof Identifier
            && $call->name->toLowerString() === 'collection'
            && $call->class instanceof Name
            && $this->isResourceClass($this->subjectClassName($call->class, $scope->subjectReflection));
    }

    /**
     * The aggregate's type with its null arm, read as getAttribute() reads `{relation}_{function}_{column}`: the
     * model's own accessor or `@property` as declared, then its built-in cast, then a user's mapping of the related
     * column's type for MIN() or MAX(), then what the driver returns. A count is `number`; null when nothing types it.
     *
     * @return ValueExpressionResult|null
     */
    private function typedAggregate(?string $function, CallArguments $args, AnalysisScope $scope): ?array
    {
        if ($function === 'count') {
            return ['type' => 'number', 'optional' => false];
        }

        $relationship = $args->named('relationship')?->value;
        $column = $args->named('column')?->value;

        if ($function === null || $scope->modelClass === null || ! $relationship instanceof String_ || ! $column instanceof String_) {
            return null;
        }

        $relationKey = Str::snake($relationship->value);
        $attribute = Str::finish($relationKey.'_'.$function.'_', $column->value);
        $declared = $this->resolveModelAttributeTypeInfo($attribute, $scope);

        if ($declared['type'] !== 'unknown') {
            return ValueResult::withAttributeChannels(['type' => $declared['type'], 'optional' => false], $declared);
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $cast = $resolver->getInstance($scope->modelClass)?->getCasts()[$attribute] ?? null;

        // A built-in cast hands the SQL NULL back untouched. Laravel reads its own cast names before any class, which
        // matters for `datetime`: PHP's class lookup ignores case and finds `DateTime`.
        $castType = is_string($cast) && ($resolver->isDateFamilyCast($cast) || ! class_exists(Str::before($cast, ':')))
            ? LaravelTsPublish::castToTsType($cast)['type']
            : 'unknown';

        if ($castType !== 'unknown') {
            return ['type' => ValueResult::withNullArm($castType), 'optional' => false];
        }

        // The attribute names the relation snake-cased, so the relation is the method whose snake case matches.
        $relation = $resolver->getRelations($scope->modelClass)
            ?->first(fn (array $relation): bool => Str::snake($relation['name']) === $relationKey);

        if ($relation !== null) {
            DependencyRecorder::recordClass($relation['related']);
        }

        $columns = $relation === null ? null : $resolver->getAttributes($relation['related']);
        $columnType = $columns?->firstWhere('name', $column->value)['type'] ?? null;

        // MIN() and MAX() keep the column's type, so a user's mapping for it wins here as it does for the column.
        if ($columnType !== null && in_array($function, ['min', 'max'], true)
            && (new TypeScriptMap)->isCustomMapped($columnType)
        ) {
            return ['type' => ValueResult::withNullArm(LaravelTsPublish::toTsType($columnType)['type']), 'optional' => false];
        }

        $driver = $resolver->connectionDriver($scope->modelClass);
        $type = $columnType === null || $driver === null ? null : AggregateValueType::of($function, $columnType, $driver);

        return $type === null ? null : ['type' => ValueResult::withNullArm($type), 'optional' => false];
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
        $default = FirstClassCallableHandler::heldCallable($default, $scope);
        $default = $passed === null ? $default : FirstClassCallableHandler::passingOneValue($default);

        return $scope->readClaimedClosure(
            $default,
            $passed === null ? 0 : 1,
            $engine,
            fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall($default)),
            $passed === null ? null : fn () => $this->bindPassedValue(
                $default,
                $passed['value'],
                $passed['resolved'],
                $passed['outer'],
                $scope,
                $engine,
                keepNull: true,
            ),
        );
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
     * The type `value($value, ...$args)` produces, `unknown` included, binding a closure's first parameter to $argument
     * (an expression, a typed value, or null for none) and the rest to their defaults; null when no value is written,
     * it is a literal null or an EnumResource wrap, so the caller keeps its attribute answer.
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

        $value = FirstClassCallableHandler::heldCallable($value, $scope);
        $value = $argument === null ? $value : FirstClassCallableHandler::passingOneValue($value);

        return $scope->readClaimedClosure(
            $value,
            $argument === null ? 0 : 1,
            $engine,
            fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall($value)),
            $argument === null ? null : fn () => $this->bindValueArgument($value, $argument, $scope, $engine),
        );
    }

    /**
     * Bind the first parameter of a value closure to the one argument Laravel passes it: a variadic one to its list.
     *
     * @param  Expr|ValueExpressionResult  $argument
     */
    private function bindValueArgument(Expr $value, Expr|array $argument, AnalysisScope $scope, ExpressionEngine $engine): void
    {
        $this->bindVariadicList($value, $argument, $scope, $engine);

        // A variadic parameter collects the argument into a list, so it never holds the argument itself.
        $name = AnalysisScope::firstParameterName($value);

        if ($name === null) {
            return;
        }

        if ($argument instanceof Expr) {
            $scope->closureParamExprBindings[$name] = $argument;
        } elseif ($argument['type'] !== 'unknown') {
            $scope->varValueBindings[$name] = $argument;
        }
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
     * Bind a whenLoaded() closure to the relation it is passed: a variadic parameter collects the relation into a list,
     * and a first parameter holds its model, its whole collection or its morphTo targets.
     */
    private function bindLoadedRelation(Expr $closure, ?Expr $relationship, AnalysisScope $scope, ExpressionEngine $engine): void
    {
        $relationInfo = $relationship instanceof String_ ? $this->resolveModelRelationTypeInfo($relationship->value, $scope) : null;

        if ($relationInfo === null) {
            return;
        }

        // A variadic parameter collects the relation into a list, which never holds null: the closure runs only for a
        // loaded value that is not null.
        if ($relationInfo['modelFqcn'] !== null || $relationInfo['morphFqcns'] !== []) {
            $this->bindVariadicList($closure, [
                'type' => ValueResult::stripNullArm($relationInfo['type']),
                'optional' => false,
                ...($relationInfo['modelFqcn'] !== null
                    ? ['modelFqcn' => $relationInfo['modelFqcn']]
                    : ['embeddedModelFqcns' => $relationInfo['morphFqcns']]),
            ], $scope, $engine);
        }

        $name = AnalysisScope::firstParameterName($closure);

        if ($name !== null && $relationInfo['modelFqcn'] !== null) {
            if (str_ends_with($relationInfo['type'], '[]')) {
                $scope->varCollectionBindings[$name] = [
                    'type' => $relationInfo['type'],
                    'modelFqcn' => $relationInfo['modelFqcn'],
                ];
            } else {
                $scope->varModelBindings[$name] = $relationInfo['modelFqcn'];
            }
        }

        // A morphTo names no single model, so its param holds any one of the targets: bind them all and let each
        // reader union them.
        if ($name !== null && $relationInfo['modelFqcn'] === null && $relationInfo['morphFqcns'] !== []) {
            $scope->varClassBindings[$name] = $relationInfo['morphFqcns'];
        }
    }

    /**
     * What whenNotNull() sends for a closure or first-class callable, which is never null: value() calls it with
     * nothing, so its parameters hold their defaults and its own null stays; null for any other value.
     *
     * @return ValueExpressionResult|null
     */
    private function calledWithNothing(Expr $value, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $this->isCallableValue($value)) {
            return null;
        }

        if ($this->closureRequiresArguments($value)) {
            $this->warnOfUnpassedArgument('whenNotNull', $value, $scope);
        }

        return $scope->readClaimedClosure($value, 0, $engine, fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall($value)));
    }

    /**
     * Whether a value is a closure, an arrow function or a first-class callable: a Closure, which value() calls.
     */
    private function isCallableValue(Expr $value): bool
    {
        return $value instanceof ClosureExpr
            || $value instanceof ArrowFunction
            || ($value instanceof CallLike && $value->isFirstClassCallable());
    }

    /**
     * Warn that a when(), unless() or whenNotNull() closure requires an argument Laravel never passes, so it throws
     * whenever it runs.
     */
    private function warnOfUnpassedArgument(string $method, Expr $closure, AnalysisScope $scope): void
    {
        AnalysisWarnings::addOnce($scope->subjectReflection->getName(), sprintf(
            '%s() on line %d calls its closure with no arguments, so the closure throws ArgumentCountError whenever it '
            .'runs. Read the value inside the closure instead of taking it as a parameter.',
            $method,
            $closure->getStartLine(),
        ));
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
     * variable held before the claim, the one model a filled value holds, or the value's own resolved type. A value no
     * rule types binds nothing, and is only noted, for a join that leaves it out to name.
     *
     * @param  ValueExpressionResult|null  $resolvedValue
     * @param  NameBindingsSnapshot  $outer
     * @param  ReceiverType|null  $model  from filledModel(); null for a default, which is passed the blank value
     */
    private function bindPassedValue(
        Expr $callback,
        Expr $value,
        ?array $resolvedValue,
        array $outer,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        bool $keepNull = false,
        ?ReceiverType $model = null,
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

        // The callback runs only for a filled value, so the parameter holding it is not null; a default's may be.
        if (! $keepNull) {
            $this->proveNonNull([$firstParam->var], $scope);
        }

        if ($this->isThisPropertyFetch($value)) {
            $scope->closureParamExprBindings[$name] = $value;

            return;
        }

        if ($value instanceof Variable) {
            $scope->copyBindings($value, $name, $outer);

            return;
        }

        if ($resolvedValue === null) {
            return;
        }

        // A model binds as a helper's argument does, so a new one keeps its unsaved mark.
        if ($model !== null) {
            $binder = resolve(ParameterBinder::class);
            $filled = [...$resolvedValue, 'type' => ValueResult::stripNullArm($resolvedValue['type'])];
            $binding = $binder->passedBinding($filled, $model);

            if ($binding !== null) {
                $binder->bindPassed([$name => $binding], $scope);
            }

            return;
        }

        if ($resolvedValue['type'] !== 'unknown') {
            $scope->varValueBindings[$name] = $resolvedValue;
        } else {
            $scope->passedValues[$name] = $value;
        }
    }

    /**
     * The one model a value holds once transform() finds it filled, so neither a `?->` nor its null arm reaches the
     * callback: the class the receiver resolver finds, with a `new` one's unsaved mark, else the model the value's type
     * names; null for any other value, and for a variable or a `$this->prop` read, which bind by what they are.
     *
     * @param  ValueExpressionResult  $resolvedValue
     */
    private function filledModel(Expr $value, array $resolvedValue, AnalysisScope $scope): ?ReceiverType
    {
        if ($value instanceof Variable || $this->isThisPropertyFetch($value)) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($value, $scope);

        if ($receiver !== null && count($receiver->classes) === 1 && $receiver->models() === $receiver->classes) {
            return $receiver->withShortCircuit(false);
        }

        $model = $resolvedValue['modelFqcn'] ?? null;

        return $model !== null
            && is_a($model, Model::class, true)
            && ValueResult::stripNullArm($resolvedValue['type']) === class_basename($model)
                ? ReceiverType::of($model)
                : null;
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
