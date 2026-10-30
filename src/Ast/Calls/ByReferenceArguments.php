<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
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

    /** A named group in a PCRE pattern, whatever letters its name uses: `(?<name>`, `(?P<name>` or `(?'name'`. */
    private const string NAMED_GROUP = '/\(\?(?:P?<(?![=!])|\')/';

    /** The sort flags that compare int keys as numbers, so ksort() leaves a list's keys in order. */
    private const array NUMERIC_SORT_FLAGS = ['SORT_REGULAR', 'SORT_NUMERIC'];

    /** The built-ins that only add values to the array they take, unlike array_splice(), which can remove any key. */
    private const array ADDS_ONLY = ['array_push', 'array_unshift'];

    /** What a built-in leaves in the variable a by-reference parameter takes, by function and parameter name. */
    private const array EFFECTS = [
        'array_pop' => ['array' => ByReferenceEffect::KeepsList],
        'array_push' => ['array' => ByReferenceEffect::Appends],
        'array_shift' => ['array' => ByReferenceEffect::KeepsList],
        'array_splice' => ['array' => ByReferenceEffect::Appends],
        'array_unshift' => ['array' => ByReferenceEffect::Appends],
        'array_walk' => ['array' => ByReferenceEffect::WritesElements],
        'array_walk_recursive' => ['array' => ByReferenceEffect::WritesLeaves],
        'arsort' => ['array' => ByReferenceEffect::KeepsObject],
        'asort' => ['array' => ByReferenceEffect::KeepsObject],
        'krsort' => ['array' => ByReferenceEffect::KeepsObject],
        'ksort' => ['array' => ByReferenceEffect::KeepsAny],
        'natcasesort' => ['array' => ByReferenceEffect::KeepsObject],
        'natsort' => ['array' => ByReferenceEffect::KeepsObject],
        'preg_match' => ['matches' => ByReferenceEffect::WritesMatches],
        'preg_replace' => ['count' => ByReferenceEffect::KeepsNumber],
        'preg_replace_callback' => ['count' => ByReferenceEffect::KeepsNumber],
        'rsort' => ['array' => ByReferenceEffect::KeepsList],
        'shuffle' => ['array' => ByReferenceEffect::KeepsList],
        'sort' => ['array' => ByReferenceEffect::KeepsList],
        'str_ireplace' => ['count' => ByReferenceEffect::KeepsNumber],
        'str_replace' => ['count' => ByReferenceEffect::KeepsNumber],
        'uasort' => ['array' => ByReferenceEffect::KeepsObject],
        // A comparator can put a list's keys out of order.
        'uksort' => ['array' => ByReferenceEffect::KeepsObject],
        'usort' => ['array' => ByReferenceEffect::KeepsList],
    ];

    /**
     * The values of the given arguments of a call that land in a by-reference parameter of the callee it names.
     *
     * @param  array<Arg>  $passed  the call's arguments the caller asks about, keyed by position
     * @param  ReflectionClass<object>|null  $runtime  the class `$this` and `static` name, but for the declaring
     *                                                 class's private `$this->m()`; null names neither
     * @param  ReflectionClass<object>|null  $declaring  the class `self` names, the using class in a trait's body; null
     *                                                   names neither it nor `parent`
     * @return list<Expr>
     */
    public function of(CallLike $call, array $passed, ?ReflectionClass $runtime, ?ReflectionClass $declaring): array
    {
        $callee = $passed === [] || $call->isFirstClassCallable() ? null : $this->callee($call, $runtime, $declaring);

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
                $effect = $effects[$parameter?->getName() ?? ''] ?? ByReferenceEffect::Replaces;

                // The matches type a whole variable; a key the call writes them into stays unread.
                if ($effect === ByReferenceEffect::WritesMatches) {
                    return $argument instanceof Variable && $this->capturesStringList($call) ? $effect : ByReferenceEffect::Replaces;
                }

                // A string comparison puts `10` before `2`, keying a list out of order.
                return $effect === ByReferenceEffect::KeepsAny && ! $this->comparesKeysAsNumbers($call)
                    ? ByReferenceEffect::KeepsObject
                    : $effect;
            }
        }

        return ByReferenceEffect::Replaces;
    }

    /**
     * Whether a call leaves every string key of a variable it passes by reference standing: a sort that keeps each key,
     * or array_push() and array_unshift(), which only add values.
     */
    public function keepsStringKeys(CallLike $call, Expr $argument): bool
    {
        return match ($this->effect($call, $argument)) {
            ByReferenceEffect::KeepsAny, ByReferenceEffect::KeepsObject => true,
            ByReferenceEffect::Appends => $call instanceof FuncCall && $call->name instanceof Name
                && in_array($this->namedFunction($call->name)?->getName(), self::ADDS_ONLY, true),
            default => false,
        };
    }

    /**
     * The function or method a call names, or null when its receiver or name is computed or cannot be loaded.
     *
     * A method PHP cannot find goes to `__call()` or `__callStatic()`, which take their arguments by value.
     *
     * @param  ReflectionClass<object>|null  $runtime
     * @param  ReflectionClass<object>|null  $declaring
     */
    private function callee(
        CallLike $call,
        ?ReflectionClass $runtime,
        ?ReflectionClass $declaring,
    ): ?ReflectionFunctionAbstract {
        try {
            if ($call instanceof FuncCall && $call->name instanceof Name) {
                return $this->namedFunction($call->name);
            }

            if ($call instanceof New_ && $call->class instanceof Name) {
                return $this->namedClass($call->class, $runtime, $declaring)?->getConstructor();
            }

            if ($call instanceof MethodCall && $call->var instanceof Variable && $call->var->name === 'this'
                && $call->name instanceof Identifier && $runtime !== null && $declaring !== null
            ) {
                $method = $call->name->toString();
                $owner = AnalysisScope::memberClassOf($declaring, $runtime, $method, true);

                // Which parameters take a reference is the class's declaration, so a change to it must rebuild this.
                DependencyRecorder::recordClass($owner->getName());

                return $owner->getMethod($method);
            }

            if ($call instanceof StaticCall && $call->class instanceof Name && $call->name instanceof Identifier) {
                return $this->namedClass($call->class, $runtime, $declaring)?->getMethod($call->name->toString());
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    /**
     * Whether a key sort's flags, when it passes any, are a constant that compares int keys as numbers.
     */
    private function comparesKeysAsNumbers(CallLike $call): bool
    {
        $flags = CallArguments::fromNames($call->getArgs(), ['array', 'flags'])->named('flags')?->value;

        return $flags === null
            || ($flags instanceof ConstFetch && in_array($flags->name->toString(), self::NUMERIC_SORT_FLAGS, true));
    }

    /**
     * Whether a preg_match() call writes a list of strings: it passes no flags, which nest each group, and its pattern
     * is a literal naming no group, since a named group keys the matches as an object.
     */
    private function capturesStringList(CallLike $call): bool
    {
        $arguments = CallArguments::fromNames($call->getArgs(), ['pattern', 'subject', 'matches', 'flags', 'offset']);
        $pattern = $arguments->named('pattern')?->value;

        return $arguments->named('flags') === null
            && $pattern instanceof String_
            && preg_match(self::NAMED_GROUP, $pattern->value) !== 1;
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
     * @param  ReflectionClass<object>|null  $runtime
     * @param  ReflectionClass<object>|null  $declaring
     * @return ReflectionClass<object>|null
     */
    private function namedClass(Name $name, ?ReflectionClass $runtime, ?ReflectionClass $declaring): ?ReflectionClass
    {
        $class = match (true) {
            ! $name->isSpecialClassName() => $name->toString(),
            $runtime === null || $declaring === null => null,
            default => AnalysisScope::relativeClassOf($name, $declaring, $runtime)?->getName(),
        };

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        // Which parameters take a reference is the class's declaration, so a change to it must rebuild this.
        DependencyRecorder::recordClass($class);

        return new ReflectionClass($class);
    }
}
