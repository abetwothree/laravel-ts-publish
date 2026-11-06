<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\ResolvesCallbackValues;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use ReflectionMethod;

/**
 * `$x->when($value, $callback, $default)` and `unless()` where `$x` runs Laravel's own `Conditionable` method: the
 * callback's value or the default's, with `$x`'s own JSON in place of a null either returns and of a default not
 * passed. The key is always sent.
 *
 * An API resource's own `$this->when()` is JsonResource's, ConditionalMethodHandler's. With fewer than two arguments
 * the call returns a HigherOrderWhenProxy, and a query's is ReceiverClassResolver::conditionalQuery()'s.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ReadArgument from ParameterBinder
 *
 * @phpstan-type ConditionableReceiver = array{type: ReceiverType, shortCircuits: bool}
 *
 * @internal
 */
final class ConditionableCallHandler implements ExpressionHandler
{
    use ResolvesCallbackValues;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall)
            || $expr->isFirstClassCallable()
            || ! $expr->name instanceof Identifier
            || ! in_array($expr->name->toLowerString(), ['when', 'unless'], true)
            || count($expr->getArgs()) < 2
        ) {
            return null;
        }

        $method = $expr->name->toLowerString();
        $arguments = CallArguments::for($expr, new ReflectionMethod(Conditionable::class, $method));
        $callables = $this->callables($arguments);
        $receiver = $callables === null ? null : $this->receiver($expr, $method, $scope);

        if ($callables === null || $receiver === null) {
            return null;
        }

        $object = $expr->var;
        $condition = $arguments->named('value')?->value;
        $passedAsWritten = array_filter([$this->isThis($object) ? null : $object, $this->calledFirst($condition) ? null : $condition]);

        if ($this->shadowsPassed($callables, array_values($passedAsWritten))) {
            return null;
        }

        // A declined read leaves neither the dropped arms it raised nor their warnings, as the call was never read.
        return DroppedUnionArms::provisional(
            fn (): ?array => $this->conditionalValue($callables, $object, $condition, $receiver, $scope, $engine),
            static fn (?array $value): bool => $value !== null,
        );
    }

    /**
     * The callables when() may run, the callback and then any default, a `null` one read as the object it hands back;
     * null where no callback is passed, or an unpacked argument may hold anything.
     *
     * @return non-empty-list<Expr>|null
     */
    private function callables(CallArguments $arguments): ?array
    {
        $callback = $arguments->named('callback')?->value;
        $default = $arguments->named('default')?->value;

        if ($arguments->hasUnpack() || $callback === null) {
            return null;
        }

        return $default === null ? [$callback] : [$callback, $default];
    }

    /**
     * The union of what each callable's call returns, with the object's own JSON in place of a null and of a default
     * not passed; null where a call or the object reads less than Laravel sends.
     *
     * @param  non-empty-list<Expr>  $callables
     * @param  ConditionableReceiver  $receiver
     * @return ValueExpressionResult|null
     */
    private function conditionalValue(
        array $callables,
        Expr $object,
        ?Expr $condition,
        array $receiver,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        $dropped = DroppedUnionArms::dropped();
        $passed = $this->passedArguments($callables, $object, $condition, $receiver, $scope, $engine);
        $arms = array_map(
            fn (Expr $callable): array => ValueResult::calledValue($this->callbackValueOfRead($callable, $passed, $scope, $engine)),
            $callables,
        );

        // An arm read with a value left out would publish the null that value left as the object.
        if (DroppedUnionArms::dropped() !== $dropped || ! array_all($arms, $this->readsWhatIsSent(...))) {
            return null;
        }

        $sendsItself = count($callables) === 1 || array_any($arms, fn (array $arm): bool => $this->mayBeNull($arm['type']));
        $itself = $sendsItself ? $this->ownJson($receiver['type']->classes) : null;

        if ($sendsItself && $itself === null) {
            return null;
        }

        $value = ValueResult::unionResults([...$this->nonNullArms($arms), ...($itself === null ? [] : [$itself])]);

        return $receiver['shortCircuits'] ? [...$value, 'type' => TsTypeString::withNullArm($value['type'])] : $value;
    }

    /**
     * What Laravel passes each callable, read in the caller's scope: the object with the receiver it holds, then the
     * condition, unless a Closure, which Laravel calls first. A position no callable takes reads nothing.
     *
     * @param  non-empty-list<Expr>  $callables
     * @param  ConditionableReceiver  $receiver
     * @return list<ReadArgument>
     */
    private function passedArguments(
        array $callables,
        Expr $object,
        ?Expr $condition,
        array $receiver,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): array {
        $unread = ['value' => ValueResult::unknown(), 'receiver' => null];
        // The object a when() runs on is never null, as a `?->` that meets null runs no call.
        $value = $this->isThis($object) ? $this->ownJson($receiver['type']->classes) ?? ValueResult::unknown() : $engine->resolve($object);

        return [
            $this->takesPosition($callables, 0)
                ? ['value' => [...$value, 'type' => TsTypeString::withoutNullArm($value['type'])], 'receiver' => $receiver['type']->withShortCircuit(false)]
                : $unread,
            $condition !== null && ! $this->calledFirst($condition) && $this->takesPosition($callables, 1)
                ? ['value' => $engine->resolve($condition), 'receiver' => resolve(ReceiverClassResolver::class)->resolve($condition, $scope)]
                : $unread,
        ];
    }

    /**
     * Whether a callable names a parameter after a variable read by the object or the condition the call is written
     * with, as passed: a reading the call keeps conservative, though both are read in the caller's scope.
     *
     * @param  non-empty-list<Expr>  $callables
     * @param  list<Expr>  $passed
     */
    private function shadowsPassed(array $callables, array $passed): bool
    {
        $read = [];

        foreach ($passed as $value) {
            foreach (new NodeFinder()->findInstanceOf($value, Variable::class) as $variable) {
                $read[] = $variable->name;
            }
        }

        foreach ($callables as $callable) {
            foreach ($callable instanceof ArrowFunction || $callable instanceof ClosureExpr ? $callable->params : [] as $param) {
                if ($param->var instanceof Variable && in_array($param->var->name, $read, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The classes of the object a `when()` or `unless()` call runs Conditionable's own method on, and whether a `?->`
     * can end the call as null; null where it runs any other method.
     *
     * @return ConditionableReceiver|null
     */
    private function receiver(MethodCall|NullsafeMethodCall $call, string $method, AnalysisScope $scope): ?array
    {
        if ($call->var instanceof Variable && $call->var->name === 'this') {
            return $this->runsConditionable($scope->thisMemberClass($method, true)->getName(), $method)
                ? ['type' => ReceiverType::of($scope->runtime->class->getName())->asRuntimeInstance(), 'shortCircuits' => false]
                : null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);

        // A join's write naming no class, or an intersection's other member, is an object the classes do not describe.
        if ($receiver === null || $receiver->intersections !== [] || $receiver->leftOut !== [] || $receiver->tagOnly !== []
            || ! array_all($receiver->classes, fn (string $class): bool => $this->runsConditionable($class, $method))
        ) {
            return null;
        }

        return ['type' => $receiver, 'shortCircuits' => $receiver->shortCircuits || $call instanceof NullsafeMethodCall];
    }

    /**
     * Whether a class's method of that name is Conditionable's own, on an object that is no model and no query: a model
     * sends the call to its query, which conditionalQuery() reads.
     */
    private function runsConditionable(string $class, string $method): bool
    {
        if (! method_exists($class, $method) || is_a($class, Model::class, true) || is_a($class, Builder::class, true)) {
            return false;
        }

        $reached = new ReflectionMethod($class, $method);
        $own = new ReflectionMethod(Conditionable::class, $method);

        return $reached->getFileName() === $own->getFileName() && $reached->getStartLine() === $own->getStartLine();
    }

    /**
     * Whether a callable's position can take a value: a closure declaring a parameter there or a variadic one, or any
     * other callable, which is passed every value.
     *
     * @param  non-empty-list<Expr>  $callables
     */
    private function takesPosition(array $callables, int $position): bool
    {
        return array_any($callables, static fn (Expr $callable): bool => ! ($callable instanceof ArrowFunction || $callable instanceof ClosureExpr)
            || count($callable->params) > $position
            || array_any($callable->params, static fn (Param $param): bool => $param->variadic));
    }

    /**
     * Whether the expression is `$this`.
     */
    private function isThis(Expr $expr): bool
    {
        return $expr instanceof Variable && $expr->name === 'this';
    }

    /**
     * Whether a condition is a Closure, which Laravel calls with the object before it tests the result.
     */
    private function calledFirst(?Expr $condition): bool
    {
        return $condition instanceof ArrowFunction || $condition instanceof ClosureExpr || ($condition !== null && CallableForms::isFirstClassCallable($condition));
    }

    /**
     * The JSON json_encode() writes for an object of any of the classes, or null where one has none the package knows.
     *
     * @param  non-empty-list<class-string>  $classes
     * @return ValueExpressionResult|null
     */
    private function ownJson(array $classes): ?array
    {
        $own = [];

        foreach ($classes as $class) {
            $json = resolve(ValueResolver::class)->resolveSerializedObject($class);

            if ($json === null || $json['type'] === 'unknown') {
                return null;
            }

            $own[] = $json;
        }

        return count($own) === 1 ? $own[0] : ValueResult::unionResults($own);
    }

    /**
     * Each callable's value without the null Laravel replaces with the object, leaving out one that is only null.
     *
     * @param  list<ValueExpressionResult>  $arms
     * @return list<ValueExpressionResult>
     */
    private function nonNullArms(array $arms): array
    {
        $kept = [];

        foreach ($arms as $arm) {
            $members = array_diff(TsTypeString::splitTopLevelUnion($arm['type']), ['null', 'void']);

            if ($members !== []) {
                $kept[] = [...$arm, 'type' => implode(' | ', $members)];
            }
        }

        return $kept;
    }

    /**
     * Whether a call's value reads as what it sends: no `unknown` arm, and no closure or prop object, which Laravel
     * hands back uncalled for json_encode() to write as `{}`.
     *
     * @param  ValueExpressionResult  $arm
     */
    private function readsWhatIsSent(array $arm): bool
    {
        return ! in_array('unknown', TsTypeString::splitTopLevelUnion($arm['type']), true) && ValueResult::heldNullsOf($arm) === null;
    }

    /**
     * Whether a callable's value may be null, which Laravel replaces with the object: a `null` or `void` member.
     */
    private function mayBeNull(string $type): bool
    {
        return array_intersect(TsTypeString::splitTopLevelUnion($type), ['null', 'void']) !== [];
    }
}
