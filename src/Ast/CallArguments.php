<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * A call's arguments read by parameter name or by position, whichever way the caller wrote them.
 *
 * passedCount() emulates PHP's func_num_args(): a named argument for position N counts as passing 0..N,
 * which is the test Laravel's conditional family makes to tell an omitted argument from an explicit null.
 * Parameter names come from reflection of the real target, memoized per callable — never a fixed table.
 */
final class CallArguments
{
    /** @var array<string, list<string>> Reflected parameter names by position, keyed by callable. */
    private static array $parameterNames = [];

    /**
     * @param  array<int, Arg>  $positional  arguments written positionally, by position
     * @param  array<string, Arg>  $named  arguments written by name, by that name
     * @param  list<string>  $paramNames  the target's declared parameter names, by position
     */
    private function __construct(
        private readonly array $positional,
        private readonly array $named,
        private readonly array $paramNames,
        private readonly bool $unpack,
        private readonly int $passedCount,
    ) {}

    /**
     * Read a call against its reflected target. A first-class callable carries no arguments at all.
     */
    public static function for(CallLike $call, ReflectionFunctionAbstract $target): self
    {
        $args = $call->isFirstClassCallable() ? [] : $call->getArgs();

        return self::fromNames($args, self::parameterNames($target));
    }

    /**
     * Read a call against an explicit parameter-name list, for a target reflection cannot reach.
     *
     * @param  array<array-key, Arg>  $args
     * @param  list<string>  $paramNames
     */
    public static function fromNames(array $args, array $paramNames): self
    {
        $positional = [];
        $named = [];
        $unpack = false;
        $highest = -1;
        $position = 0;

        foreach ($args as $arg) {
            if ($arg->unpack) {
                $unpack = true;

                continue;
            }

            if ($arg->name === null) {
                $positional[$position] = $arg;
                $highest = max($highest, $position);
                $position++;

                continue;
            }

            $name = $arg->name->toString();
            $named[$name] = $arg;
            $declared = array_search($name, $paramNames, true);

            if ($declared !== false) {
                $highest = max($highest, $declared);
            }
        }

        return new self($positional, $named, $paramNames, $unpack, $highest + 1);
    }

    /**
     * The argument bound to a position: written there, or written by that position's parameter name.
     */
    public function at(int $position): ?Arg
    {
        if (isset($this->positional[$position])) {
            return $this->positional[$position];
        }

        $name = $this->paramNames[$position] ?? null;

        return $name === null ? null : ($this->named[$name] ?? null);
    }

    /**
     * The argument bound to a parameter name: written by that name, or at that name's declared position.
     */
    public function named(string $name): ?Arg
    {
        if (isset($this->named[$name])) {
            return $this->named[$name];
        }

        $position = $this->positionOf($name);

        return $position === null ? null : ($this->positional[$position] ?? null);
    }

    /**
     * The declared position of a parameter name, or null when the target declares no such parameter.
     */
    public function positionOf(string $name): ?int
    {
        $position = array_search($name, $this->paramNames, true);

        return $position === false ? null : $position;
    }

    /**
     * What func_num_args() would report: the highest bound position plus one. A spread is not counted.
     */
    public function passedCount(): int
    {
        return $this->passedCount;
    }

    /**
     * Whether any argument is a `...$spread`, which makes every position after it unknowable.
     */
    public function hasUnpack(): bool
    {
        return $this->unpack;
    }

    /**
     * Whether the call carries no arguments at all — no positional, no named, no spread.
     */
    public function isEmpty(): bool
    {
        return $this->positional === [] && $this->named === [] && ! $this->unpack;
    }

    /**
     * The target's parameter names by position, reflected once per callable.
     *
     * @return list<string>
     */
    private static function parameterNames(ReflectionFunctionAbstract $target): array
    {
        $key = $target instanceof ReflectionMethod ? $target->class.'::'.$target->name : $target->getName();

        return self::$parameterNames[$key] ??= array_map(
            fn (ReflectionParameter $parameter): string => $parameter->getName(),
            $target->getParameters(),
        );
    }
}
