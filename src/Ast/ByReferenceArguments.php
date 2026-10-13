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
 * write scan both read a call's writes through it, and the local write scan what a built-in leaves in each.
 *
 * @internal
 */
final class ByReferenceArguments
{
    /** By-reference functions that only move an array's internal pointer, so the value keeps its type. */
    private const array POINTER_FUNCTIONS = ['end', 'next', 'prev', 'reset'];

    /** What a built-in leaves in the variable a by-reference parameter takes, by function and parameter name. */
    private const array EFFECTS = [
        'array_push' => ['array' => ByReferenceEffect::Appends],
        'array_unshift' => ['array' => ByReferenceEffect::Appends],
        'array_walk' => ['array' => ByReferenceEffect::WritesElements],
        'array_walk_recursive' => ['array' => ByReferenceEffect::WritesElements],
        'arsort' => ['array' => ByReferenceEffect::KeepsObject],
        'asort' => ['array' => ByReferenceEffect::KeepsObject],
        'krsort' => ['array' => ByReferenceEffect::KeepsObject],
        'ksort' => ['array' => ByReferenceEffect::KeepsAny],
        'natcasesort' => ['array' => ByReferenceEffect::KeepsObject],
        'natsort' => ['array' => ByReferenceEffect::KeepsObject],
        'preg_replace' => ['count' => ByReferenceEffect::KeepsNumber],
        'preg_replace_callback' => ['count' => ByReferenceEffect::KeepsNumber],
        'rsort' => ['array' => ByReferenceEffect::KeepsList],
        'shuffle' => ['array' => ByReferenceEffect::KeepsList],
        'sort' => ['array' => ByReferenceEffect::KeepsList],
        'str_ireplace' => ['count' => ByReferenceEffect::KeepsNumber],
        'str_replace' => ['count' => ByReferenceEffect::KeepsNumber],
        'uasort' => ['array' => ByReferenceEffect::KeepsObject],
        'uksort' => ['array' => ByReferenceEffect::KeepsAny],
        'usort' => ['array' => ByReferenceEffect::KeepsList],
    ];

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
        $arguments = [];

        foreach ($passed as $index => $arg) {
            if ($this->parameterAt($parameters, $index, $arg)?->isPassedByReference()) {
                $arguments[] = $arg->value;
            }
        }

        return $arguments;
    }

    /**
     * What a call leaves in a variable it passes to a by-reference parameter: a built-in's effect on that parameter,
     * else Replaces, as for every user function and method.
     */
    public function effect(CallLike $call, Expr $argument): ByReferenceEffect
    {
        $function = $call instanceof FuncCall && $call->name instanceof Name && ! $call->isFirstClassCallable()
            ? $this->namedFunction($call->name)
            : null;
        $effects = $function !== null && $function->isInternal() ? self::EFFECTS[$function->getName()] ?? [] : [];

        foreach ($effects === [] ? [] : $call->getArgs() as $index => $arg) {
            if ($arg->value === $argument) {
                $parameter = $this->parameterAt($function?->getParameters() ?? [], $index, $arg);

                return $effects[$parameter?->getName() ?? ''] ?? ByReferenceEffect::Replaces;
            }
        }

        return ByReferenceEffect::Replaces;
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
     * The parameter an argument at a position, or of a name, lands in.
     *
     * @param  list<ReflectionParameter>  $parameters
     */
    private function parameterAt(array $parameters, int|string $index, Arg $arg): ?ReflectionParameter
    {
        $named = $arg->name?->toString();
        $last = $parameters === [] ? null : $parameters[array_key_last($parameters)];
        $parameter = $named === null
            ? (is_int($index) ? $parameters[$index] ?? null : null)
            : array_find($parameters, fn (ReflectionParameter $known): bool => $known->getName() === $named);

        // PHP collects a surplus positional or unknown named argument into a trailing variadic.
        return $parameter ?? ($last?->isVariadic() ? $last : null);
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
