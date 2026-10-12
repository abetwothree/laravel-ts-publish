<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A call read where it is written, so the method it runs can be read with what the call passes: how each argument
 * binds a parameter whose declaration binds nothing, or one declaring an abstract class, interface or union it narrows.
 *
 * @phpstan-import-type PassedBinding from ParameterBinder
 *
 * @internal
 */
final readonly class CallSite
{
    /**
     * Read a call in the scope and engine it is written in.
     */
    public function __construct(
        private CallLike $call,
        private AnalysisScope $scope,
        private ExpressionEngine $engine,
    ) {}

    /**
     * The memo key suffix for what a call binds, so two calls that bind alike share one answer; empty when the call
     * binds nothing, so it keeps the method's own key.
     *
     * @param  array<string, PassedBinding>  $passed
     */
    public static function signature(array $passed): string
    {
        return $passed === [] ? '' : '@args:'.hash('xxh128', serialize($passed));
    }

    /**
     * Whether the call writes any argument a parameter could take, a first-class callable's `...` being none.
     */
    public function passesArguments(): bool
    {
        return ! $this->call->isFirstClassCallable() && $this->call->getArgs() !== [];
    }

    /**
     * How this call's arguments bind each parameter of the method it runs whose declaration binds nothing, or which an
     * argument narrows, read in the caller's scope; an unpacked or first-class call, and an argument nothing can type,
     * bind nothing.
     *
     * @param  bool  $valueRead  the call is read as a value, not spread in place
     * @return array<string, PassedBinding>
     */
    public function passedTo(ReflectionMethod $method, bool $valueRead = false): array
    {
        if (! $this->passesArguments()) {
            return [];
        }

        $arguments = CallArguments::for($this->call, $method);

        if ($arguments->hasUnpack()) {
            return [];
        }

        $passed = [];

        foreach ($method->getParameters() as $parameter) {
            $argument = $arguments->named($parameter->getName());
            $binding = $argument === null || ($valueRead && $this->passesUncalledCallable($argument->value))
                ? null
                : $this->bindingFor($parameter, $method, $argument->value);

            if ($binding !== null) {
                $passed[$parameter->getName()] = $binding;
            }
        }

        return $passed;
    }

    /**
     * How one argument binds its parameter: whatever it holds where the declaration binds nothing, else the narrower
     * classes it holds where the declaration names an abstract class, interface or union; null otherwise.
     *
     * @return PassedBinding|null
     */
    private function bindingFor(ReflectionParameter $parameter, ReflectionMethod $method, Expr $argument): ?array
    {
        $binder = resolve(ParameterBinder::class);
        $takes = $binder->takesPassedValue($parameter, $method);
        $declared = $takes ? null : $binder->narrowableClasses($parameter);

        if (! $takes && $declared === null) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($argument, $this->scope);

        if ($declared !== null && ! $binder->narrows($declared, $receiver)) {
            return null;
        }

        return $binder->passedBinding($this->engine->resolve($argument), $receiver, refines: $declared !== null);
    }

    /**
     * Whether an argument is a closure or first-class callable whose value hangs on whether the method calls it:
     * Laravel sends `{}` for one held and what it returns for one called; Inertia calls each, so it sends the latter.
     */
    private function passesUncalledCallable(Expr $argument): bool
    {
        return ! $this->engine->invokesCallables()
            && ($argument instanceof Closure
                || $argument instanceof ArrowFunction
                || ($argument instanceof CallLike && $argument->isFirstClassCallable()));
    }
}
