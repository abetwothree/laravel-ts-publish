<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use PhpParser\Node\Expr\CallLike;
use ReflectionMethod;

/**
 * A call read where it is written, so the method it runs can be read with what the call passes: how each argument
 * binds a parameter whose declaration binds nothing.
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
     * How this call's arguments bind each parameter of the method it runs whose declaration binds nothing, read in the
     * caller's scope; an unpacked or first-class call, and an argument nothing can type, bind nothing.
     *
     * @return array<string, PassedBinding>
     */
    public function passedTo(ReflectionMethod $method): array
    {
        if (! $this->passesArguments()) {
            return [];
        }

        $arguments = CallArguments::for($this->call, $method);

        if ($arguments->hasUnpack()) {
            return [];
        }

        $binder = resolve(ParameterBinder::class);
        $passed = [];

        foreach ($method->getParameters() as $parameter) {
            $argument = $arguments->named($parameter->getName());

            if ($argument === null || ! $binder->takesPassedValue($parameter, $method)) {
                continue;
            }

            $binding = $binder->passedBinding(
                $this->engine->resolve($argument->value),
                resolve(ReceiverClassResolver::class)->resolve($argument->value, $this->scope),
            );

            if ($binding !== null) {
                $passed[$parameter->getName()] = $binding;
            }
        }

        return $passed;
    }
}
