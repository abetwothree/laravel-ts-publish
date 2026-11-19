<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * A call's arguments read by parameter name or by position, whichever way the caller wrote them, an unpacked array
 * literal's items among them as PHP binds each: a string key by that name, any other key in place.
 *
 * passedCount() emulates PHP's func_num_args(): a named argument for position N counts as passing 0..N,
 * which is the test Laravel's conditional family makes to tell an omitted argument from an explicit null.
 * Parameter names are reflected from the real target, memoized only where that target's identity is stable.
 *
 * @internal
 */
final class CallArguments
{
    /** Marks an argument that stands for an unpacked literal's item. */
    public const string UNPACKED_ITEM = 'unpackedItem';

    /** @var array<string, list<string>> Reflected parameter names by position, keyed by callable. */
    private static array $parameterNames = [];

    /**
     * @param  list<Arg>  $positional  arguments bound positionally, by position
     * @param  array<string, Arg>  $named  arguments bound by name, by that name
     * @param  list<string>  $paramNames  the target's declared parameter names, by position
     * @param  list<Arg>  $unexpanded  the unpacks it could not expand, as written
     */
    private function __construct(
        private readonly array $positional,
        private readonly array $named,
        private readonly array $paramNames,
        private readonly array $unexpanded,
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
        $unexpanded = [];
        $highest = -1;

        foreach ($args as $arg) {
            // A literal after an unpack it cannot expand starts at a position the call does not spell.
            $items = $arg->unpack ? ($unexpanded === [] ? self::unpackedItems($arg->value) : null) : [$arg];

            if ($items === null) {
                $unexpanded[] = $arg;

                continue;
            }

            foreach ($items as $item) {
                $name = $item->name?->toString();
                $position = $name === null ? count($positional) : array_search($name, $paramNames, true);

                if ($name === null) {
                    $positional[] = $item;
                } else {
                    $named[$name] = $item;
                }

                $highest = $position === false ? $highest : max($highest, $position);
            }
        }

        return new self($positional, $named, $paramNames, $unexpanded, $highest + 1);
    }

    /**
     * The value a `collect($value)` call wraps; null for any other expression, a first-class callable included.
     */
    public static function collectArgument(Expr $expr): ?Expr
    {
        if (! $expr instanceof FuncCall
            || ! $expr->name instanceof Name
            || $expr->name->getLast() !== 'collect'
            || $expr->isFirstClassCallable()
        ) {
            return null;
        }

        return self::for($expr, new ReflectionFunction('collect'))->named('value')?->value;
    }

    /**
     * Clears the memoized parameter-name cache, so one test run's reflection can't leak into another's.
     */
    public static function reset(): void
    {
        self::$parameterNames = [];
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
     * Every argument as the call binds it, positional then named, then each unpack it could not expand.
     *
     * @return list<Arg>
     */
    public function bound(): array
    {
        return [...$this->positional, ...array_values($this->named), ...$this->unexpanded];
    }

    /**
     * The positional arguments from a position on, which a variadic parameter there collects.
     *
     * @return list<Arg>
     */
    public function from(int $position): array
    {
        return array_slice($this->positional, $position);
    }

    /**
     * The arguments bound by a name no parameter declares, which a variadic parameter collects.
     *
     * @return list<Arg>
     */
    public function undeclared(): array
    {
        return array_values(array_diff_key($this->named, array_flip($this->paramNames)));
    }

    /**
     * What func_num_args() would report: the highest bound position plus one.
     *
     * An unpack it could not expand is not counted, so this undercounts once hasUnpack() is true — check that first.
     */
    public function passedCount(): int
    {
        return $this->passedCount;
    }

    /**
     * Whether any argument is an unpack it could not expand, which makes every position after it unknowable.
     */
    public function hasUnpack(): bool
    {
        return $this->unexpanded !== [];
    }

    /**
     * The unpacks it could not expand, each as written: one of no array literal, or of a literal with a computed key,
     * an unpacked item, or a place no earlier argument fixes.
     *
     * @return list<Arg>
     */
    public function unexpanded(): array
    {
        return $this->unexpanded;
    }

    /**
     * Whether the call carries no arguments at all — no positional, no named, no unexpanded unpack.
     */
    public function isEmpty(): bool
    {
        return $this->positional === [] && $this->named === [] && $this->unexpanded === [];
    }

    /**
     * The arguments an unpacked array literal passes, its items keyed as PHP stores them; null for any other value, or
     * a literal with a key no constant spells, an unpacked item, or a keyless item PHP cannot number.
     *
     * @return list<Arg>|null
     */
    private static function unpackedItems(Expr $unpacked): ?array
    {
        if (! $unpacked instanceof Array_) {
            return null;
        }

        $items = [];

        foreach ($unpacked->items as $item) {
            $key = $item->key;

            if ($item->unpack
                || ($key !== null && ! $key instanceof Int_ && ! $key instanceof String_)
                || ($key === null && array_key_exists(PHP_INT_MAX, $items))
            ) {
                return null;
            }

            if ($key === null) {
                $items[] = $item;
            } else {
                $items[$key->value] = $item;
            }
        }

        return array_map(
            static fn (int|string $key): Arg => new Arg(
                $items[$key]->value,
                $items[$key]->byRef,
                attributes: [...$items[$key]->getAttributes(), self::UNPACKED_ITEM => true],
                name: is_string($key) ? new Identifier($key) : null,
            ),
            array_keys($items),
        );
    }

    /**
     * The target's parameter names by position, memoized only where the callable's identity is stable;
     * a closure or first-class callable is read fresh, since two unrelated ones can share a bare name.
     *
     * @return list<string>
     */
    private static function parameterNames(ReflectionFunctionAbstract $target): array
    {
        if ($target instanceof ReflectionMethod) {
            $key = $target->class.'::'.$target->name;

            return self::$parameterNames[$key] ??= self::declaredParameterNames($target);
        }

        if ($target instanceof ReflectionFunction && ! $target->isClosure()) {
            return self::$parameterNames[$target->getName()] ??= self::declaredParameterNames($target);
        }

        return self::declaredParameterNames($target);
    }

    /**
     * Every declared parameter name up to (not including) a variadic tail, which has no fixed position.
     *
     * @return list<string>
     */
    private static function declaredParameterNames(ReflectionFunctionAbstract $target): array
    {
        $names = [];

        foreach ($target->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $names[] = $parameter->getName();
        }

        return $names;
    }
}
