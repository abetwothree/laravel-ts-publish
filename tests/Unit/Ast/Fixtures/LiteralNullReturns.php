<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use LogicException;

/**
 * Untyped methods whose own returns hold a literal null, beside ones where only a value they read may hold one.
 */
final class LiteralNullReturns
{
    /** A value that may be null. */
    private ?string $stored = null;

    /** Returns null for a NULL. */
    public function returnsNull($value)
    {
        if ($value === null) {
            return null;
        }

        return 'x';
    }

    /** Returns nothing for a NULL. */
    public function returnsNothing($value)
    {
        if ($value === null) {
            return;
        }

        return 'x';
    }

    /** Falls off its end for any value but a string. */
    public function fallsOff($value)
    {
        if (is_string($value)) {
            return $value;
        }
    }

    /** Returns null through a ternary arm. */
    public function ternaryArm($value)
    {
        return $value === '' ? null : 'x';
    }

    /** Returns null through a short ternary's arm. */
    public function shortTernaryArm($value)
    {
        return $value ?: null;
    }

    /** Returns null through a ternary nested in another's arm. */
    public function nestedTernaryArm($value)
    {
        return $value === '' ? 'a' : ($value === 'b' ? null : 'c');
    }

    /** Returns null through a match arm. */
    public function matchArm($value)
    {
        return match ($value) {
            'a' => 'x',
            default => null,
        };
    }

    /** Returns null through `??`. */
    public function coalesceArm($value)
    {
        return $value ?? null;
    }

    /** Returns a property that may hold null. */
    public function readsProperty()
    {
        return $this->stored;
    }

    /** Returns what a helper that returns null gives. */
    public function callsHelper()
    {
        return $this->helper();
    }

    /** Returns a list a closure returning null maps. */
    public function closureReturnsNull()
    {
        return array_map(function ($item) {
            return null;
        }, ['a']);
    }

    /** Replaces a NULL through `??` with a string. */
    public function coalesceDefault($value)
    {
        return $value ?? 'd';
    }

    /** Declares mixed, so it throws rather than falls off its end. */
    public function declaredMixed($value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        throw new LogicException('Not a string.');
    }

    /** A helper that returns null. */
    private function helper()
    {
        return null;
    }
}
