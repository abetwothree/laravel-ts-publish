<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;

/**
 * The arguments a call passes to a by-reference parameter of the callee it names: a function, a method of `$this`,
 * `self`, `static`, `parent` or a named class, or a named class's constructor. The property default scan and the local
 * write scan both read a call's writes through it.
 *
 * @internal
 */
final class ByReferenceArguments
{
    /** By-reference functions that only move an array's internal pointer, so the value keeps its type. */
    private const array POINTER_FUNCTIONS = ['end', 'next', 'prev', 'reset'];

    /**
     * The values of the given arguments of a call that land in a by-reference parameter of the callee it names.
     *
     * @param  array<Arg>  $passed  the call's arguments the caller asks about, keyed by position
     * @param  ReflectionClass<object>|null  $subject  `$this`, `self` and `static` bind to it; null names none of them
     * @param  ReflectionClass<object>|null  $declaration  the class or trait whose body holds the call; `parent`
     *                                                     binds to its parent
     * @return list<Expr>
     */
    public function of(CallLike $call, array $passed, ?ReflectionClass $subject, ?ReflectionClass $declaration): array
    {
        $callee = $passed === [] || $call->isFirstClassCallable() ? null : $this->callee($call, $subject, $declaration);

        if ($callee === null
            || ($callee instanceof ReflectionFunction && in_array($callee->getName(), self::POINTER_FUNCTIONS, true))
        ) {
            return [];
        }

        $parameters = $callee->getParameters();
        $last = $parameters === [] ? null : $parameters[array_key_last($parameters)];
        $arguments = [];

        foreach ($passed as $index => $arg) {
            $named = $arg->name?->toString();
            $parameter = $named === null
                ? (is_int($index) ? $parameters[$index] ?? null : null)
                : array_find($parameters, fn (ReflectionParameter $known): bool => $known->getName() === $named);

            // PHP collects a surplus positional or unknown named argument into a trailing variadic.
            $parameter ??= $last?->isVariadic() ? $last : null;

            if ($parameter?->isPassedByReference()) {
                $arguments[] = $arg->value;
            }
        }

        return $arguments;
    }

    /**
     * The function or method a call names, or null when its receiver or name is computed or cannot be loaded.
     *
     * A method PHP cannot find goes to `__call()` or `__callStatic()`, which take their arguments by value.
     *
     * @param  ReflectionClass<object>|null  $subject
     * @param  ReflectionClass<object>|null  $declaration
     */
    private function callee(
        CallLike $call,
        ?ReflectionClass $subject,
        ?ReflectionClass $declaration,
    ): ?ReflectionFunctionAbstract {
        try {
            if ($call instanceof FuncCall && $call->name instanceof Name) {
                return $this->namedFunction($call->name);
            }

            if ($call instanceof New_ && $call->class instanceof Name) {
                return $this->namedClass($call->class, $subject, $declaration)?->getConstructor();
            }

            if ($call instanceof MethodCall && $call->var instanceof Variable && $call->var->name === 'this'
                && $call->name instanceof Identifier
            ) {
                return $subject?->getMethod($call->name->toString());
            }

            if ($call instanceof StaticCall && $call->class instanceof Name && $call->name instanceof Identifier) {
                return $this->namedClass($call->class, $subject, $declaration)?->getMethod($call->name->toString());
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    /**
     * The function a name calls, tried in its namespace first, then globally, as PHP does.
     */
    private function namedFunction(Name $name): ?ReflectionFunction
    {
        $namespaced = $name->getAttribute('namespacedName');

        foreach ([$namespaced instanceof Name ? $namespaced->toString() : null, $name->toString()] as $candidate) {
            if ($candidate !== null && function_exists($candidate)) {
                return new ReflectionFunction($candidate);
            }
        }

        return null;
    }

    /**
     * The class a static call or `new` names, or null when it cannot be loaded.
     *
     * @param  ReflectionClass<object>|null  $subject
     * @param  ReflectionClass<object>|null  $declaration
     * @return ReflectionClass<object>|null
     */
    private function namedClass(Name $name, ?ReflectionClass $subject, ?ReflectionClass $declaration): ?ReflectionClass
    {
        $parent = $declaration?->getParentClass();
        $class = match (strtolower($name->toString())) {
            'self', 'static' => $subject?->getName(),
            'parent' => $parent === null || $parent === false ? null : $parent->getName(),
            default => $name->toString(),
        };

        return $class !== null && class_exists($class) ? new ReflectionClass($class) : null;
    }
}
