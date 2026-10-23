<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

/**
 * What one write does to the variable it names, as the passes that end, gap or join a binding read it. Each write is
 * classified once, as VariableWrites scans it.
 *
 * @internal
 */
enum VariableWrite
{
    /** `$x = …` replaces the value. */
    case Replaces;

    /** `$x ??= …` replaces the value only where it is null. */
    case Coalesces;

    /** `.=`, `+=` and the other operators, `++` and `--` work on the value the variable holds. */
    case Compounds;

    /** `$x->prop = …` or `$x['key'] = …` writes through the variable. */
    case MemberOrOffset;

    /** `[$x] = …` writes an item of the value a list destructures. */
    case Destructures;

    /** A `foreach` key or value variable. */
    case Foreach;

    /** A `catch` variable. */
    case Catches;

    /** `unset()`. */
    case Unsets;

    /** A by-reference argument, or the array a by-reference `foreach` writes element by element. */
    case ByReference;

    /** `=&`, `global`, `static` or a by-reference `foreach` value, which another name or a call can write through. */
    case References;

    /** A closure's by-reference `use`, which every write inside that closure goes through. */
    case InClosure;

    /**
     * Whether the write leaves the variable a reference that another name, or a closure, writes through later.
     */
    public function makesReference(): bool
    {
        return $this === self::References || $this === self::InClosure;
    }
}
