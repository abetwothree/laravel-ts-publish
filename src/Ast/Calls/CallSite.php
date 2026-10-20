<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A call read where it is written, so the method it runs can be read with what the call passes: how each argument
 * binds a parameter whose declaration binds nothing, one declaring an abstract class, interface or union it narrows,
 * or one declaring a concrete model it passes as one no row backs yet.
 *
 * @phpstan-import-type PassedBinding from ParameterBinder
 *
 * @internal
 */
final readonly class CallSite
{
    use InspectsAstNodes;

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
     * @param  bool  $valueRead  the call is read as a value, not spread in place: a first-class callable binds nothing
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
            $binding = $argument === null || $this->passesUncalledCallable($argument->value, $valueRead)
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
     * classes it holds where the declaration names an abstract class, interface or union, or a model no row backs yet
     * where it names that concrete model; null otherwise.
     *
     * @return PassedBinding|null
     */
    private function bindingFor(ReflectionParameter $parameter, ReflectionMethod $method, Expr $argument): ?array
    {
        $binder = resolve(ParameterBinder::class);
        $takes = $binder->takesPassedValue($parameter, $method);
        $declared = $takes ? null : $binder->narrowableClasses($parameter);
        $model = $takes || $declared !== null ? null : $binder->declaredModel($parameter);

        if (! $takes && $declared === null && $model === null) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($argument, $this->scope);

        // A declared model binds as a row that exists; only an argument no row backs yet reads differently, as a new
        // declared model whichever of its classes it holds, since passedBinding() marks only one class unsaved.
        if ($model !== null) {
            if ($receiver === null || ! $receiver->unsaved || ! $receiver->within([$model])) {
                return null;
            }

            $held = count($receiver->classes) === 1 ? $receiver : new ReceiverType([$model], unsaved: true);

            return $binder->passedBinding($this->engine->resolve($argument), $held, refines: true);
        }

        if ($declared !== null && ! $binder->narrows($declared, $receiver)) {
            return null;
        }

        return $binder->passedBinding($this->engine->resolve($argument), $receiver, refines: $declared !== null);
    }

    /**
     * Whether an argument may hold a closure, or on a value read a first-class callable, whose value hangs on whether
     * the method calls it: Laravel sends `{}` for one held and what it returns for one called; Inertia calls each, so
     * it sends the latter. A spread reads a held first-class callable as the Closure it creates, which is `{}`.
     */
    private function passesUncalledCallable(Expr $argument, bool $valueRead): bool
    {
        return ! $this->engine->invokesCallables() && $this->mayHoldCallable($argument, $this->scope, $valueRead);
    }
}
