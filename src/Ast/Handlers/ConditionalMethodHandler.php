<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
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
 *
 * @internal
 */
final class ConditionalMethodHandler implements ExpressionHandler
{
    use InspectsAstNodes;
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
            return $this->applyConditionalDefault(['type' => 'number', 'optional' => false], $this->arguments($expr, 'whenCounted'), $scope, $engine);
        }

        if ($this->isThisMethodCall($expr, 'whenAggregated')) {
            /** @var MethodCall $expr */
            return $this->applyConditionalDefault(['type' => 'number', 'optional' => false], $this->arguments($expr, 'whenAggregated'), $scope, $engine);
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
     * Fold a conditional method's explicit default into its value arm's result.
     *
     * An explicit default always makes the property required, since Laravel then always emits the key.
     * The default's type unions in when it resolves; otherwise the value arm's own type stands alone.
     * $defaultArgCount is how many arguments Laravel invokes the default with — 0 for the value($default)
     * family, 1 for transform()'s $default($value) — and is forwarded to closureRequiresArguments().
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult
     */
    protected function applyConditionalDefault(
        array $value,
        CallArguments $args,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        int $defaultArgCount = 0,
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

        $default = $engine->resolve($defaultExpr);

        // An `unknown` on either arm carries no type to union: an unresolved default leaves the value arm
        // standing, and an unresolved value arm already admits whatever the default could produce.
        if ($default['type'] === 'unknown' || $value['type'] === 'unknown') {
            return [...$value, 'optional' => false];
        }

        $members = array_values(array_unique([
            ...LaravelTsPublish::splitTopLevelUnion($value['type']),
            ...LaravelTsPublish::splitTopLevelUnion($default['type']),
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

        $previousBindings = $scope->closureParamExprBindings;
        $this->bindClosureParamsFromCondition($condition->value, $valueArg->value, $scope);

        $inner = $engine->resolve($valueArg->value);

        $scope->closureParamExprBindings = $previousBindings;

        return $this->applyConditionalDefault($inner, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenHas('attribute') — the attribute name is the first arg string.
     *
     * The value arg (2nd) is never evaluated for its own type: Laravel invokes it with the named
     * attribute's own value, so the attribute is authoritative for type and array-ness. It IS
     * checked for EnumResource::make()/::collection() shape, since that decides whether the enum
     * channel is 'enumFqcn' (wrapped — gets the AsEnum rewrite) or 'directEnumFqcn' (read as-is).
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

        $info = $this->resolveModelAttributeTypeInfo($attribute->value, $scope);
        $result = ['type' => $info['type'], 'optional' => false];

        if ($info['enumFqcn'] !== null) {
            $valueExpr = $args->named('value')?->value;
            $wrapped = $valueExpr !== null && $this->isEnumResourceWrapCall($valueExpr);
            $result[$wrapped ? 'enumFqcn' : 'directEnumFqcn'] = $info['enumFqcn'];
        }

        return $this->applyConditionalDefault($result, $args, $scope, $engine);
    }

    /**
     * Analyze $this->whenAppended('attribute', $value, $default) — types from the named attribute,
     * the same way whenHas() does, since the appended accessor is what surfaces. Unlike whenHas()/
     * whenLoaded(), Laravel's whenAppended() invokes a Closure value with no arguments at all, so
     * only a non-first-class-callable EnumResource::make()/::collection() value is realistically
     * reachable here — still checked for consistency, since it costs nothing.
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

        $info = $this->resolveModelAttributeTypeInfo($attribute->value, $scope);
        $result = ['type' => $info['type'], 'optional' => false];

        if ($info['enumFqcn'] !== null) {
            $valueExpr = $args->named('value')?->value;
            $wrapped = $valueExpr !== null && $this->isEnumResourceWrapCall($valueExpr);
            $result[$wrapped ? 'enumFqcn' : 'directEnumFqcn'] = $info['enumFqcn'];
        }

        return $this->applyConditionalDefault($result, $args, $scope, $engine);
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
     * Analyze $this->whenExistsLoaded('relation', $value, $default) — resolves to the relation's
     * generated `{relation}_exists` flag.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenExistsLoaded(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $args = $this->arguments($call, 'whenExistsLoaded');

        if (! $args->named('relationship')?->value instanceof String_) {
            return [...ValueResult::unknown(), 'optional' => true]; // @codeCoverageIgnore
        }

        // Unlike whenLoaded(), a skipped $value is not swapped for the identity closure: value(null, …) is null.
        if ($this->valueSkipped($args)) {
            return $this->applyConditionalDefault(['type' => 'null', 'optional' => false], $args, $scope, $engine);
        }

        return $this->applyConditionalDefault(['type' => 'boolean', 'optional' => false], $args, $scope, $engine);
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
     * Analyze $this->whenLoaded('relation') or $this->whenLoaded('relation', value, default).
     *
     * A single-model relation's closure param binds to the model; a to-many relation's binds to the
     * collection type instead, since the param holds the whole collection rather than one element.
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
            $previousVarModelBindings = $scope->varModelBindings;
            $previousVarCollectionBindings = $scope->varCollectionBindings;
            $relationInfo = null;

            if ($relationship instanceof String_) {
                $relationInfo = $this->resolveModelRelationTypeInfo($relationship->value, $scope);

                if ($relationInfo['modelFqcn'] !== null) {
                    $scope->closureRelationModelClass = $relationInfo['modelFqcn'];
                }
            }

            if ($relationInfo !== null
                && $relationInfo['modelFqcn'] !== null
                && ($valueExpr instanceof ClosureExpr || $valueExpr instanceof ArrowFunction)
                && isset($valueExpr->params[0])
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

            try {
                $inner = $engine->resolve($valueExpr);
            } finally {
                $scope->closureRelationModelClass = $previousRelationModel;
                $scope->varModelBindings = $previousVarModelBindings;
                $scope->varCollectionBindings = $previousVarCollectionBindings;
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

        $previousBindings = $scope->closureParamExprBindings;
        $this->bindClosureParamsFromCondition($valueArg->value, $callbackArg->value, $scope);

        $inner = $engine->resolve($callbackArg->value);

        $scope->closureParamExprBindings = $previousBindings;

        // transform()'s default runs through the global transform() helper's $default($value) — one
        // argument — unlike the rest of the family's zero-argument value($default).
        return $this->applyConditionalDefault($inner, $args, $scope, $engine, defaultArgCount: 1);
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
     * The call's arguments mapped against JsonResource's own signature for the method, so every read
     * lands where Laravel binds it whether the caller wrote positions or names.
     */
    private function arguments(MethodCall $call, string $method): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(JsonResource::class, $method));
    }

    /**
     * Bind a closure's first parameter to the `$this->propName` expression found in a `when()` condition,
     * so `EnumResource::make($status)` resolves as if it were `EnumResource::make($this->status)`.
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

        if ($firstParam === null) {
            return;
        }

        if ($firstParam->var instanceof Variable && is_string($firstParam->var->name)) {
            $scope->closureParamExprBindings[$firstParam->var->name] = $thisPropExpr;
        }
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
